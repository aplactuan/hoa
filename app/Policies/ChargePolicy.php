<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    public function requestWaiver(User $user, Charge $charge): bool
    {
        return $this->create($user);
    }

    public function approveWaiver(User $user, Charge $charge): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ChargesView)
            || $user->hasPermission(Permission::OwnerPortalView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Charge $charge): bool
    {
        return $user->hasPermission(Permission::ChargesView)
            || ($user->hasPermission(Permission::OwnerPortalView)
                && $user->ownsCurrentProperty($charge->property_id));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator) || $user->hasRole(RoleName::OfficeStaff) || $user->hasRole(RoleName::Treasurer);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Charge $charge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Charge $charge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Charge $charge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Charge $charge): bool
    {
        return false;
    }
}
