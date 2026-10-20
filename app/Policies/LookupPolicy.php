<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LookupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::MasterDataManage);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::MasterDataManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::MasterDataManage);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::MasterDataManage);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::MasterDataManage);
    }
}
