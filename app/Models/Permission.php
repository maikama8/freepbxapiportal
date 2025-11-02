<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
    ];

    /**
     * Get users who have this permission directly assigned
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withPivot('granted')
                    ->withTimestamps();
    }

    /**
     * Get user permissions for this permission
     */
    public function userPermissions()
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * Scope permissions by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get all permissions grouped by category
     */
    public static function getGroupedByCategory()
    {
        return static::orderBy('category')->orderBy('display_name')->get()->groupBy('category');
    }
}
