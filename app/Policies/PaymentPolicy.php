<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function requestVoid(User $user, Payment $payment): bool
    {
        return $user->hasRole(RoleName::Treasurer);
    }

    public function approveVoid(User $user, Payment $payment): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ReceiptsView)
            || $user->hasPermission(Permission::OwnerPortalView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission(Permission::ReceiptsView)
            || ($user->hasPermission(Permission::OwnerPortalView)
                && $user->ownsCurrentProperty($payment->property_id));
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
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
