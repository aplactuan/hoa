<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PropertiesView)
            || $user->hasPermission(Permission::OwnerPortalView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Property $property): bool
    {
        return $user->hasPermission(Permission::PropertiesView)
            || ($user->hasPermission(Permission::OwnerPortalView) && $user->ownsCurrentProperty($property));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator) || $user->hasRole(RoleName::OfficeStaff);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Property $property): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Property $property): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Property $property): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Property $property): bool
    {
        return false;
    }
}
