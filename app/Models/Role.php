<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    // Define available roles as constants
    const ADMIN = 'admin';
    const CUSTOMER = 'customer';
    const OPERATOR = 'operator';

    /**
     * Get all available roles
     */
    public static function getAvailableRoles(): array
    {
        return [
            self::ADMIN => 'Administrator',
            self::CUSTOMER => 'Customer',
            self::OPERATOR => 'Operator',
        ];
    }

    /**
     * Get role display name
     */
    public static function getDisplayName(string $role): string
    {
        $roles = self::getAvailableRoles();
        return $roles[$role] ?? ucfirst($role);
    }

    /**
     * Check if role exists
     */
    public static function exists(string $role): bool
    {
        return array_key_exists($role, self::getAvailableRoles());
    }

    /**
     * Get permissions for a specific role
     */
    public static function getPermissions(string $role): array
    {
        if (!self::exists($role)) {
            return [];
        }

        return \DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role', $role)
            ->pluck('permissions.name')
            ->toArray();
    }

    /**
     * Get all permissions grouped by role
     */
    public static function getAllRolePermissions(): array
    {
        $rolePermissions = [];
        
        foreach (self::getAvailableRoles() as $role => $displayName) {
            $rolePermissions[$role] = [
                'display_name' => $displayName,
                'permissions' => self::getPermissions($role),
            ];
        }

        return $rolePermissions;
    }

    /**
     * Assign permission to role
     */
    public static function assignPermission(string $role, string $permissionName): bool
    {
        if (!self::exists($role)) {
            return false;
        }

        $permission = Permission::where('name', $permissionName)->first();
        if (!$permission) {
            return false;
        }

        \DB::table('role_permissions')->updateOrInsert(
            ['role' => $role, 'permission_id' => $permission->id],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Remove permission from role
     */
    public static function removePermission(string $role, string $permissionName): bool
    {
        if (!self::exists($role)) {
            return false;
        }

        $permission = Permission::where('name', $permissionName)->first();
        if (!$permission) {
            return false;
        }

        return \DB::table('role_permissions')
            ->where('role', $role)
            ->where('permission_id', $permission->id)
            ->delete() > 0;
    }

    /**
     * Check if role has permission
     */
    public static function hasPermission(string $role, string $permissionName): bool
    {
        if (!self::exists($role)) {
            return false;
        }

        return \DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role', $role)
            ->where('permissions.name', $permissionName)
            ->exists();
    }

    /**
     * Get role hierarchy (for inheritance purposes)
     */
    public static function getHierarchy(): array
    {
        return [
            self::ADMIN => 3,     // Highest level
            self::OPERATOR => 2,  // Middle level
            self::CUSTOMER => 1,  // Lowest level
        ];
    }

    /**
     * Check if role A has higher or equal level than role B
     */
    public static function hasHigherOrEqualLevel(string $roleA, string $roleB): bool
    {
        $hierarchy = self::getHierarchy();
        return ($hierarchy[$roleA] ?? 0) >= ($hierarchy[$roleB] ?? 0);
    }

    /**
     * Get users count by role
     */
    public static function getUsersCount(): array
    {
        return \DB::table('users')
            ->select('role', \DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();
    }
}