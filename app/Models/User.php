<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use App\Enums\Permission;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasAttachments, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles');
    }

    /**
     * @return HasMany<Owner, $this>
     */
    public function ownerRecords(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * @return HasMany<Owner, $this>
     */
    public function currentOwnerRecords(): HasMany
    {
        return $this->ownerRecords()->where('status', 'Current');
    }

    /**
     * @return Builder<Property>
     */
    public function currentOwnedProperties(): Builder
    {
        return Property::query()->whereHas('owners', function (Builder $query): void {
            $query->where('user_id', $this->id)->where('status', 'Current');
        });
    }

    public function ownsCurrentProperty(Property|int $property): bool
    {
        $propertyId = $property instanceof Property ? $property->getKey() : $property;

        return $this->currentOwnerRecords()->where('property_id', $propertyId)->exists();
    }

    public function assignRole(RoleName|string $role): void
    {
        $name = $role instanceof RoleName ? $role->value : $role;
        $roleModel = Role::query()->where('name', $name)->firstOrFail();

        $this->roles()->sync([$roleModel->id]);
        $this->unsetRelation('roles');
    }

    public function hasRole(RoleName $role): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('name', $role->value);
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->value : $permission;
        $this->loadMissing('roles.permissions');

        return $this->roles->contains(function (Role $role) use ($name): bool {
            return $role->permissions->contains('name', $name);
        });
    }

    public function homeRoute(): string
    {
        if ($this->hasRole(RoleName::Owner)) {
            return 'owner.dashboard';
        }

        if ($this->hasPermission(Permission::GateView) && ! $this->hasPermission(Permission::DashboardView)) {
            return 'gate-check';
        }

        if ($this->hasPermission(Permission::DashboardView)) {
            return 'dashboard';
        }

        return 'profile.edit';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
