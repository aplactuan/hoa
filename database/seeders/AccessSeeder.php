<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    /**
     * Seed roles and permissions from the access matrix.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                ['label' => $permission->label()],
            );
        }

        $roles = config('access.roles');

        if (! is_array($roles)) {
            return;
        }

        foreach ($roles as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }

            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => is_string($definition['label'] ?? null) ? $definition['label'] : $name],
            );

            $permissionNames = $definition['permissions'] ?? [];
            $permissionNames = is_array($permissionNames) ? $permissionNames : [];

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
