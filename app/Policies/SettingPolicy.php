<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->hasPermission(Permission::SettingsManage);
    }

    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }
}
