<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\UserPermission;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $role, ?User $assignedBy = null): bool
    {
        if (!Role::exists($role)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $oldRole = $user->role;
        $user->role = $role;
        $user->save();

        // Log the role change
        if ($assignedBy) {
            AuditLog::log('role_assigned', $assignedBy, $user, null, [
                'old_role' => $oldRole,
                'new_role' => $role,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
            ]);
        }

        return true;
    }

    /**
     * Grant permission to user (overrides role permission)
     */
    public function grantPermission(User $user, string $permissionName, ?User $grantedBy = null): bool
    {
        $permission = Permission::where('name', $permissionName)->first();
        if (!$permission) {
            throw new \InvalidArgumentException("Permission not found: {$permissionName}");
        }

        $userPermission = UserPermission::grant($user, $permission);

        // Log the permission grant
        if ($grantedBy) {
            AuditLog::log('permission_granted', $grantedBy, $user, null, [
                'permission_name' => $permissionName,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
            ]);
        }

        return true;
    }

    /**
     * Revoke permission from user (explicitly deny)
     */
    public function revokePermission(User $user, string $permissionName, ?User $revokedBy = null): bool
    {
        $permission = Permission::where('name', $permissionName)->first();
        if (!$permission) {
            throw new \InvalidArgumentException("Permission not found: {$permissionName}");
        }

        $userPermission = UserPermission::revoke($user, $permission);

        // Log the permission revocation
        if ($revokedBy) {
            AuditLog::log('permission_revoked', $revokedBy, $user, null, [
                'permission_name' => $permissionName,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
            ]);
        }

        return true;
    }

    /**
     * Remove permission assignment from user (inherit from role)
     */
    public function removePermissionAssignment(User $user, string $permissionName, ?User $removedBy = null): bool
    {
        $permission = Permission::where('name', $permissionName)->first();
        if (!$permission) {
            throw new \InvalidArgumentException("Permission not found: {$permissionName}");
        }

        $removed = UserPermission::remove($user, $permission);

        // Log the permission removal
        if ($removed && $removedBy) {
            AuditLog::log('permission_assignment_removed', $removedBy, $user, null, [
                'permission_name' => $permissionName,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
            ]);
        }

        return $removed;
    }

    /**
     * Get user's effective permissions (role + individual permissions)
     */
    public function getUserEffectivePermissions(User $user): array
    {
        // Get role permissions
        $rolePermissions = Role::getPermissions($user->role);

        // Get individual user permissions
        $userPermissions = $user->userPermissions()
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->select('permissions.name', 'user_permissions.granted')
            ->get()
            ->keyBy('name');

        $effectivePermissions = [];

        // Start with role permissions
        foreach ($rolePermissions as $permission) {
            $effectivePermissions[$permission] = [
                'granted' => true,
                'source' => 'role',
                'role' => $user->role,
            ];
        }

        // Apply individual user permissions (overrides)
        foreach ($userPermissions as $permission => $data) {
            $effectivePermissions[$permission] = [
                'granted' => $data->granted,
                'source' => 'user',
                'role' => $user->role,
            ];
        }

        return $effectivePermissions;
    }

    /**
     * Get user's permission summary
     */
    public function getUserPermissionSummary(User $user): array
    {
        $effectivePermissions = $this->getUserEffectivePermissions($user);
        $grantedPermissions = array_keys(array_filter($effectivePermissions, fn($p) => $p['granted']));
        $deniedPermissions = array_keys(array_filter($effectivePermissions, fn($p) => !$p['granted']));

        return [
            'role' => $user->role,
            'role_display_name' => Role::getDisplayName($user->role),
            'total_permissions' => count($effectivePermissions),
            'granted_permissions' => $grantedPermissions,
            'denied_permissions' => $deniedPermissions,
            'granted_count' => count($grantedPermissions),
            'denied_count' => count($deniedPermissions),
            'effective_permissions' => $effectivePermissions,
        ];
    }

    /**
     * Bulk assign permissions to role
     */
    public function bulkAssignPermissionsToRole(string $role, array $permissionNames, ?User $assignedBy = null): array
    {
        if (!Role::exists($role)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $results = [];
        
        DB::transaction(function () use ($role, $permissionNames, $assignedBy, &$results) {
            foreach ($permissionNames as $permissionName) {
                try {
                    $success = Role::assignPermission($role, $permissionName);
                    $results[$permissionName] = $success;
                    
                    if ($success && $assignedBy) {
                        AuditLog::log('role_permission_assigned', $assignedBy, null, null, [
                            'role' => $role,
                            'permission_name' => $permissionName,
                        ]);
                    }
                } catch (\Exception $e) {
                    $results[$permissionName] = false;
                }
            }
        });

        return $results;
    }

    /**
     * Bulk remove permissions from role
     */
    public function bulkRemovePermissionsFromRole(string $role, array $permissionNames, ?User $removedBy = null): array
    {
        if (!Role::exists($role)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $results = [];
        
        DB::transaction(function () use ($role, $permissionNames, $removedBy, &$results) {
            foreach ($permissionNames as $permissionName) {
                try {
                    $success = Role::removePermission($role, $permissionName);
                    $results[$permissionName] = $success;
                    
                    if ($success && $removedBy) {
                        AuditLog::log('role_permission_removed', $removedBy, null, null, [
                            'role' => $role,
                            'permission_name' => $permissionName,
                        ]);
                    }
                } catch (\Exception $e) {
                    $results[$permissionName] = false;
                }
            }
        });

        return $results;
    }

    /**
     * Check if user can manage another user (based on role hierarchy)
     */
    public function canManageUser(User $manager, User $target): bool
    {
        // Users can always manage themselves (for profile updates)
        if ($manager->id === $target->id) {
            return true;
        }

        // Check if manager has user management permissions
        if (!$manager->hasPermission('users.edit')) {
            return false;
        }

        // Check role hierarchy - can only manage users with lower or equal role level
        return Role::hasHigherOrEqualLevel($manager->role, $target->role);
    }

    /**
     * Get permissions that can be assigned by a user
     */
    public function getAssignablePermissions(User $user): array
    {
        // Admins can assign all permissions
        if ($user->isAdmin()) {
            return Permission::all()->toArray();
        }

        // Operators can assign customer permissions only
        if ($user->isOperator()) {
            $customerPermissions = Role::getPermissions(Role::CUSTOMER);
            return Permission::whereIn('name', $customerPermissions)->get()->toArray();
        }

        // Customers cannot assign permissions
        return [];
    }

    /**
     * Get roles that can be assigned by a user
     */
    public function getAssignableRoles(User $user): array
    {
        $allRoles = Role::getAvailableRoles();

        // Admins can assign all roles
        if ($user->isAdmin()) {
            return $allRoles;
        }

        // Operators can only assign customer role
        if ($user->isOperator()) {
            return [Role::CUSTOMER => $allRoles[Role::CUSTOMER]];
        }

        // Customers cannot assign roles
        return [];
    }

    /**
     * Validate permission assignment
     */
    public function validatePermissionAssignment(User $assigner, User $target, string $permissionName): array
    {
        $errors = [];

        // Check if assigner has permission to manage users
        if (!$this->canManageUser($assigner, $target)) {
            $errors[] = 'You do not have permission to manage this user';
        }

        // Check if permission exists
        if (!Permission::where('name', $permissionName)->exists()) {
            $errors[] = 'Permission does not exist';
        }

        // Check if assigner can assign this permission
        $assignablePermissions = $this->getAssignablePermissions($assigner);
        $assignablePermissionNames = array_column($assignablePermissions, 'name');
        
        if (!in_array($permissionName, $assignablePermissionNames)) {
            $errors[] = 'You do not have permission to assign this permission';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate role assignment
     */
    public function validateRoleAssignment(User $assigner, User $target, string $role): array
    {
        $errors = [];

        // Check if assigner has permission to manage users
        if (!$this->canManageUser($assigner, $target)) {
            $errors[] = 'You do not have permission to manage this user';
        }

        // Check if role exists
        if (!Role::exists($role)) {
            $errors[] = 'Role does not exist';
        }

        // Check if assigner can assign this role
        $assignableRoles = $this->getAssignableRoles($assigner);
        
        if (!array_key_exists($role, $assignableRoles)) {
            $errors[] = 'You do not have permission to assign this role';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}