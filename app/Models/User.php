<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'invited_at', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable {
        HasRoles::hasRole as protected traitHasRole;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        $this->loadMissing('roles');

        $this->setRelation('roles', $this->roles->filter(fn ($role) => $role->is_active));

        return $this->traitHasRole($roles, $guard);
    }

    public function getPermissionsViaRoles(): Collection
    {
        $this->loadMissing('roles', 'roles.permissions');

        return $this->roles->filter(fn ($role) => $role->is_active)
            ->flatMap(fn ($role) => $role->permissions)
            ->sort()->values();
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::Owner->value);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->withTimestamps();
    }

    public function hasTenantProfile(): bool
    {
        return $this->tenant()->exists();
    }

    public function canAccessProperty(Property|int $property): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $propertyId = $property instanceof Property ? $property->id : $property;

        return $this->properties()->whereKey($propertyId)->exists();
    }
}
