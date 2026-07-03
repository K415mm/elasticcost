<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user has any of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isClient(): bool
    {
        return $this->hasRole('client');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isSalesManager(): bool
    {
        return $this->hasRole('sales_manager');
    }

    public function isPartner(): bool
    {
        return $this->hasRole('partner');
    }

    public function isCeo(): bool
    {
        return $this->hasRole('ceo');
    }

    /**
     * Check if the user's role has a specific permission.
     */
    public function hasPermission(string $permissionKey): bool
    {
        return RolePermission::roleHasPermission($this->role, $permissionKey);
    }

    /**
     * Check if the user's role has any of the given permissions.
     *
     * @param  array<string>  $permissionKeys
     */
    public function hasAnyPermission(array $permissionKeys): bool
    {
        foreach ($permissionKeys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all allowed permission keys for this user's role.
     */
    public function allowedPermissions(): Collection
    {
        return RolePermission::allowedKeysForRole($this->role);
    }
}
