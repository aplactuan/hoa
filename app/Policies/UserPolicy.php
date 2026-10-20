<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersView);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::UsersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
