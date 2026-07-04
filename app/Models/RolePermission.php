<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RolePermission extends Model
{
    protected $fillable = ['role', 'permission_id', 'is_allowed'];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
        ];
    }

    /**
     * Get the permission this role permission belongs to.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Check if a role has a specific permission key.
     */
    public static function roleHasPermission(string $role, string $permissionKey): bool
    {
        return Cache::remember(
            "perm:{$role}:{$permissionKey}",
            now()->addHours(1),
            fn () => self::where('role', $role)
                ->where('is_allowed', true)
                ->whereHas('permission', fn ($q) => $q->where('key', $permissionKey))
                ->exists()
        );
    }

    /**
     * Get all allowed permission keys for a role.
     */
    public static function allowedKeysForRole(string $role): Collection
    {
        return Cache::remember(
            "perm_keys:{$role}",
            now()->addHours(1),
            fn () => self::where('role', $role)
                ->where('is_allowed', true)
                ->with('permission')
                ->get()
                ->pluck('permission.key')
        );
    }

    /**
     * Flush permission cache for a specific role.
     */
    public static function flushRoleCache(string $role): void
    {
        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            Cache::forget("perm:{$role}:{$permission->key}");
        }

        Cache::forget("perm_keys:{$role}");
    }

    /**
     * Flush permission cache for all roles.
     */
    public static function flushAllCache(): void
    {
        $roles = ['client', 'manager', 'sales_manager', 'partner', 'ceo'];

        foreach ($roles as $role) {
            self::flushRoleCache($role);
        }
    }
}
