<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    protected RolePermissionService $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Display roles and their permissions
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::getAllRolePermissions();
            $permissions = Permission::getGroupedByCategory();
            $usersCount = Role::getUsersCount();

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'users_count' => $usersCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles and permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permissions for a specific role
     */
    public function show(string $role): JsonResponse
    {
        try {
            if (!Role::exists($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $permissions = Role::getPermissions($role);
            $allPermissions = Permission::getGroupedByCategory();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role,
                    'display_name' => Role::getDisplayName($role),
                    'permissions' => $permissions,
                    'all_permissions' => $allPermissions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, string $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            if (!Role::exists($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $results = $this->rolePermissionService->bulkAssignPermissionsToRole(
                $role,
                $request->permissions,
                $request->user()
            );

            $successCount = count(array_filter($results));
            $totalCount = count($results);

            return response()->json([
                'success' => true,
                'message' => "Assigned {$successCount} of {$totalCount} permissions to role {$role}",
                'data' => [
                    'results' => $results,
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions to role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove permissions from role
     */
    public function removePermissions(Request $request, string $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            if (!Role::exists($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            $results = $this->rolePermissionService->bulkRemovePermissionsFromRole(
                $role,
                $request->permissions,
                $request->user()
            );

            $successCount = count(array_filter($results));
            $totalCount = count($results);

            return response()->json([
                'success' => true,
                'message' => "Removed {$successCount} of {$totalCount} permissions from role {$role}",
                'data' => [
                    'results' => $results,
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove permissions from role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update role permissions (replace all)
     */
    public function updatePermissions(Request $request, string $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            if (!Role::exists($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Get current permissions
            $currentPermissions = Role::getPermissions($role);

            // Remove all current permissions
            if (!empty($currentPermissions)) {
                $this->rolePermissionService->bulkRemovePermissionsFromRole(
                    $role,
                    $currentPermissions,
                    $request->user()
                );
            }

            // Assign new permissions
            $results = $this->rolePermissionService->bulkAssignPermissionsToRole(
                $role,
                $request->permissions,
                $request->user()
            );

            $successCount = count(array_filter($results));
            $totalCount = count($results);

            return response()->json([
                'success' => true,
                'message' => "Updated permissions for role {$role}. Assigned {$successCount} of {$totalCount} permissions",
                'data' => [
                    'results' => $results,
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                    'updated_permissions' => $request->permissions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}