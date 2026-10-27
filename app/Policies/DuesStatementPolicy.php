<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\DuesStatement;
use App\Models\User;

class DuesStatementPolicy
{
    public function requestAdjustment(User $user, DuesStatement $statement): bool
    {
        return $user->hasRole(RoleName::Treasurer);
    }

    public function approveAdjustment(User $user, DuesStatement $statement): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::StatementsView)
            || $user->hasPermission(Permission::OwnerPortalView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DuesStatement $duesStatement): bool
    {
        return $user->hasPermission(Permission::StatementsView)
            || ($user->hasPermission(Permission::OwnerPortalView)
                && $user->ownsCurrentProperty($duesStatement->property_id));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Treasurer);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DuesStatement $duesStatement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DuesStatement $duesStatement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DuesStatement $duesStatement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DuesStatement $duesStatement): bool
    {
        return false;
    }
}
