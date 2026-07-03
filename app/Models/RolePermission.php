<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
        return self::where('role', $role)
            ->where('is_allowed', true)
            ->whereHas('permission', fn ($q) => $q->where('key', $permissionKey))
            ->exists();
    }

    /**
     * Get all allowed permission keys for a role.
     */
    public static function allowedKeysForRole(string $role): Collection
    {
        return self::where('role', $role)
            ->where('is_allowed', true)
            ->with('permission')
            ->get()
            ->pluck('permission.key');
    }
}
