<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_id',
        'granted',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    /**
     * Get the user that owns the permission
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the permission
     */
    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Grant permission to user
     */
    public static function grant(User $user, Permission $permission): self
    {
        return static::updateOrCreate(
            [
                'user_id' => $user->id,
                'permission_id' => $permission->id,
            ],
            ['granted' => true]
        );
    }

    /**
     * Revoke permission from user
     */
    public static function revoke(User $user, Permission $permission): self
    {
        return static::updateOrCreate(
            [
                'user_id' => $user->id,
                'permission_id' => $permission->id,
            ],
            ['granted' => false]
        );
    }

    /**
     * Remove permission assignment from user
     */
    public static function remove(User $user, Permission $permission): bool
    {
        return static::where('user_id', $user->id)
                    ->where('permission_id', $permission->id)
                    ->delete() > 0;
    }
}
