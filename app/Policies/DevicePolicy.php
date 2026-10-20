<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::DevicesManage);
    }

    public function view(User $user, Device $device): bool
    {
        return $user->hasPermission(Permission::DevicesManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::DevicesManage);
    }

    public function update(User $user, Device $device): bool
    {
        return $user->hasPermission(Permission::DevicesManage);
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->hasPermission(Permission::DevicesManage);
    }
}
