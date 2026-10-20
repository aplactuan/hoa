<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator)
            || $user->hasRole(RoleName::OfficeStaff)
            || $user->hasRole(RoleName::SecurityGuard);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator) || $user->hasRole(RoleName::OfficeStaff);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->create($user);
    }
}
