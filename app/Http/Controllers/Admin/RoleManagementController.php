<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    /**
     * Display role management interface
     */
    public function index(Request $request): View
    {
        return view('admin.roles.index');
    }

    /**
     * Get roles data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Role::withCount('users');

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                  ->orWhere('display_name', 'like', "%{$searchValue}%")
                  ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        // Filter by status
        if ($request->filled('status_filter')) {
            $query->where('is_active', $request->status_filter === '1');
        }

        // Ordering
        if ($request->has('order')) {
            $columns = ['id', 'name', 'display_name', 'description', 'users_count', 'is_active', 'created_at'];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'name';
            $orderDirection = $request->order[0]['dir'] ?? 'asc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        $totalRecords = Role::count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 25;
        $roles = $query->skip($start)->take($length)->get();

        $data = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions ? count($role->permissions) : 0,
                'is_active' => $role->is_active,
                'is_system' => $role->is_system,
                'created_at' => $role->created_at->format('M d, Y'),
                'actions' => $role->id
            ];
        });

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Store new role
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'string|max:100',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'permissions' => $request->permissions ?? [],
                'is_active' => $request->boolean('is_active', true),
                'is_system' => false,
                'created_by' => auth()->id()
            ]);

            // Log the action
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'role_created',
                'description' => "Created role: {$role->display_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'permissions_count' => count($role->permissions)
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show role details
     */
    public function show(Role $role): JsonResponse
    {
        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'permissions' => $role->permissions ?? [],
            'is_active' => $role->is_active,
            'is_system' => $role->is_system,
            'users_count' => $role->users()->count(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];

        // Get users with this role
        $users = $role->users()->select('id', 'name', 'email', 'status')->get();
        $roleData['users'] = $users;

        return response()->json([
            'success' => true,
            'role' => $roleData
        ]);
    }

    /**
     * Update role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        // Prevent modification of system roles
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be modified'
            ], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', Rule::unique('roles')->ignore($role->id)],
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'string|max:100',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $oldData = [
                'name' => $role->name,
                'display_name' => $role->display_name,
                'permissions' => $role->permissions
            ];

            $role->update([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'permissions' => $request->permissions ?? [],
                'is_active' => $request->boolean('is_active', true),
                'updated_by' => auth()->id()
            ]);

            // Log the action
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'role_updated',
                'description' => "Updated role: {$role->display_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'role_id' => $role->id,
                    'old_data' => $oldData,
                    'new_data' => [
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                        'permissions' => $role->permissions
                    ]
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy(Role $role): JsonResponse
    {
        // Prevent deletion of system roles
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted'
            ], 403);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that has assigned users'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $roleName = $role->display_name;
            $role->delete();

            // Log the action
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'role_deleted',
                'description' => "Deleted role: {$roleName}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'role_name' => $role->name,
                    'display_name' => $roleName
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available permissions
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = [
            'User Management' => [
                'users.view' => 'View Users',
                'users.create' => 'Create Users',
                'users.edit' => 'Edit Users',
                'users.delete' => 'Delete Users',
                'users.balance' => 'Adjust User Balance'
            ],
            'Role Management' => [
                'roles.view' => 'View Roles',
                'roles.create' => 'Create Roles',
                'roles.edit' => 'Edit Roles',
                'roles.delete' => 'Delete Roles'
            ],
            'Billing & Rates' => [
                'rates.view' => 'View Rates',
                'rates.create' => 'Create Rates',
                'rates.edit' => 'Edit Rates',
                'rates.delete' => 'Delete Rates',
                'billing.view' => 'View Billing',
                'billing.manage' => 'Manage Billing Settings'
            ],
            'DID Management' => [
                'dids.view' => 'View DIDs',
                'dids.create' => 'Create DIDs',
                'dids.edit' => 'Edit DIDs',
                'dids.delete' => 'Delete DIDs',
                'dids.assign' => 'Assign DIDs'
            ],
            'Call Management' => [
                'calls.view' => 'View Calls',
                'calls.manage' => 'Manage Calls',
                'calls.terminate' => 'Terminate Calls',
                'cdr.view' => 'View CDR Records'
            ],
            'System Administration' => [
                'system.view' => 'View System Info',
                'system.settings' => 'Manage System Settings',
                'system.monitoring' => 'System Monitoring',
                'audit.view' => 'View Audit Logs',
                'cron.manage' => 'Manage Cron Jobs'
            ],
            'Reports & Analytics' => [
                'reports.view' => 'View Reports',
                'reports.export' => 'Export Reports',
                'analytics.view' => 'View Analytics'
            ]
        ];

        return response()->json($permissions);
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            $role = Role::findOrFail($request->role_id);

            $oldRole = $user->role;
            $user->update(['role' => $role->name]);

            // Log the action
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'role_assigned',
                'description' => "Assigned role '{$role->display_name}' to user '{$user->name}'",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'old_role' => $oldRole,
                    'new_role' => $role->name
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role statistics
     */
    public function getStatistics(): JsonResponse
    {
        $stats = [
            'total_roles' => Role::count(),
            'active_roles' => Role::where('is_active', true)->count(),
            'system_roles' => Role::where('is_system', true)->count(),
            'custom_roles' => Role::where('is_system', false)->count(),
            'roles_with_users' => Role::has('users')->count(),
            'role_distribution' => Role::withCount('users')
                ->orderByDesc('users_count')
                ->limit(10)
                ->get()
                ->map(function($role) {
                    return [
                        'name' => $role->display_name,
                        'users_count' => $role->users_count
                    ];
                })
        ];

        return response()->json($stats);
    }
}