<?php

namespace App\Policies;

use App\Enums\OwnerChangeStatus;
use App\Enums\Permission;
use App\Models\OwnerChangeRequest;
use App\Models\User;

class OwnerChangeRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::OwnerChangesManage)
            || $user->hasPermission(Permission::OwnerPortalView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return $user->hasPermission(Permission::OwnerChangesManage)
            || ($ownerChangeRequest->requested_by === $user->id
                && $user->ownsCurrentProperty($ownerChangeRequest->property_id));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OwnerPortalView)
            && $user->currentOwnerRecords()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return $user->hasPermission(Permission::OwnerChangesManage);
    }

    public function cancel(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return $ownerChangeRequest->requested_by === $user->id
            && $ownerChangeRequest->status === OwnerChangeStatus::Pending
            && $user->ownsCurrentProperty($ownerChangeRequest->property_id);
    }

    public function review(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return $this->update($user, $ownerChangeRequest)
            && $ownerChangeRequest->status === OwnerChangeStatus::Pending;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OwnerChangeRequest $ownerChangeRequest): bool
    {
        return false;
    }
}
