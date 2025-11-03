<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
        'is_active',
        'is_system',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean'
    ];

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

    /**
     * Relationship: Users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name');
    }

    /**
     * Relationship: User who created this role
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: User who last updated this role
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Add permission to role
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->update(['permissions' => array_values($permissions)]);
    }

    /**
     * Scope: Active roles only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Custom roles only (non-system)
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope: System roles only
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}