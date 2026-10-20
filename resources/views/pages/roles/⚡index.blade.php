<?php

use App\Models\Role;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Roles')] class extends Component
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    #[Computed]
    public function roles()
    {
        return Role::query()->with('permissions')->orderBy('label')->get();
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header
        :title="__('Roles')"
        :subtitle="__('Each person has one role. Permissions come from the access matrix and are assigned when the database is seeded.')"
    />

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->roles as $role)
            <section wire:key="role-{{ $role->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ $role->label }}</flux:heading>
                <ul class="mt-3 space-y-1">
                    @foreach ($role->permissions->sortBy('label') as $permission)
                        <li wire:key="permission-{{ $role->id }}-{{ $permission->id }}" class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $permission->label }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>
