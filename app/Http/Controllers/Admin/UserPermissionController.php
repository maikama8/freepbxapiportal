<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserPermissionController extends Controller
{
    protected RolePermissionService $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Get user's permissions summary
     */
    public function show(User $user): JsonResponse
    {
        try {
            // Check if current user can manage the target user
            if (!$this->rolePermissionService->canManageUser(request()->user(), $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this user\'s permissions',
                ], 403);
            }

            $permissionSummary = $this->rolePermissionService->getUserPermissionSummary($user);
            $assignablePermissions = $this->rolePermissionService->getAssignablePermissions(request()->user());
            $assignableRoles = $this->rolePermissionService->getAssignableRoles(request()->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'permission_summary' => $permissionSummary,
                    'assignable_permissions' => $assignablePermissions,
                    'assignable_roles' => $assignableRoles,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:admin,customer,operator',
        ]);

        try {
            // Validate role assignment
            $validation = $this->rolePermissionService->validateRoleAssignment(
                $request->user(),
                $user,
                $request->role
            );

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role assignment validation failed',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $this->rolePermissionService->assignRole($user, $request->role, $request->user());

            return response()->json([
                'success' => true,
                'message' => "Role '{$request->role}' assigned to user {$user->name}",
                'data' => [
                    'user_id' => $user->id,
                    'old_role' => $user->getOriginal('role'),
                    'new_role' => $request->role,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role to user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Grant permission to user
     */
    public function grantPermission(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        try {
            // Validate permission assignment
            $validation = $this->rolePermissionService->validatePermissionAssignment(
                $request->user(),
                $user,
                $request->permission
            );

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission assignment validation failed',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $this->rolePermissionService->grantPermission($user, $request->permission, $request->user());

            return response()->json([
                'success' => true,
                'message' => "Permission '{$request->permission}' granted to user {$user->name}",
                'data' => [
                    'user_id' => $user->id,
                    'permission' => $request->permission,
                    'action' => 'granted',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant permission to user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke permission from user
     */
    public function revokePermission(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        try {
            // Validate permission assignment
            $validation = $this->rolePermissionService->validatePermissionAssignment(
                $request->user(),
                $user,
                $request->permission
            );

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission assignment validation failed',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $this->rolePermissionService->revokePermission($user, $request->permission, $request->user());

            return response()->json([
                'success' => true,
                'message' => "Permission '{$request->permission}' revoked from user {$user->name}",
                'data' => [
                    'user_id' => $user->id,
                    'permission' => $request->permission,
                    'action' => 'revoked',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke permission from user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove permission assignment from user (inherit from role)
     */
    public function removePermissionAssignment(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        try {
            // Validate permission assignment
            $validation = $this->rolePermissionService->validatePermissionAssignment(
                $request->user(),
                $user,
                $request->permission
            );

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission assignment validation failed',
                    'errors' => $validation['errors'],
                ], 422);
            }

            $removed = $this->rolePermissionService->removePermissionAssignment(
                $user,
                $request->permission,
                $request->user()
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => "Permission assignment for '{$request->permission}' removed from user {$user->name}. User will inherit from role.",
                    'data' => [
                        'user_id' => $user->id,
                        'permission' => $request->permission,
                        'action' => 'assignment_removed',
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "No permission assignment found for '{$request->permission}' on user {$user->name}",
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permission assignment from user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update user permissions
     */
    public function bulkUpdatePermissions(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.name' => 'required|string|exists:permissions,name',
            'permissions.*.action' => 'required|string|in:grant,revoke,remove',
        ]);

        try {
            // Check if current user can manage the target user
            if (!$this->rolePermissionService->canManageUser($request->user(), $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to manage this user\'s permissions',
                ], 403);
            }

            $results = [];
            $successCount = 0;

            foreach ($request->permissions as $permissionData) {
                try {
                    $permissionName = $permissionData['name'];
                    $action = $permissionData['action'];

                    // Validate each permission assignment
                    $validation = $this->rolePermissionService->validatePermissionAssignment(
                        $request->user(),
                        $user,
                        $permissionName
                    );

                    if (!$validation['valid']) {
                        $results[$permissionName] = [
                            'success' => false,
                            'action' => $action,
                            'errors' => $validation['errors'],
                        ];
                        continue;
                    }

                    switch ($action) {
                        case 'grant':
                            $this->rolePermissionService->grantPermission($user, $permissionName, $request->user());
                            break;
                        case 'revoke':
                            $this->rolePermissionService->revokePermission($user, $permissionName, $request->user());
                            break;
                        case 'remove':
                            $this->rolePermissionService->removePermissionAssignment($user, $permissionName, $request->user());
                            break;
                    }

                    $results[$permissionName] = [
                        'success' => true,
                        'action' => $action,
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $results[$permissionName] = [
                        'success' => false,
                        'action' => $action,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $totalCount = count($request->permissions);

            return response()->json([
                'success' => true,
                'message' => "Updated {$successCount} of {$totalCount} permissions for user {$user->name}",
                'data' => [
                    'results' => $results,
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update user permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}