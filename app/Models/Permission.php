<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = ['key', 'label', 'description', 'category', 'icon', 'sort_order'];

    /**
     * Get all role permissions for this permission.
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Check if a specific role has this permission.
     */
    public function isAllowedForRole(string $role): bool
    {
        return $this->rolePermissions()
            ->where('role', $role)
            ->where('is_allowed', true)
            ->exists();
    }

    /**
     * Get permissions grouped by category.
     */
    public static function groupedByCategory(): Collection
    {
        return self::orderBy('sort_order')->orderBy('label')->get()->groupBy('category');
    }
}
