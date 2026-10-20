<?php

use App\Actions\InviteUser;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Livewire\DataTableComponent;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Users')] class extends DataTableComponent
{

    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public function createUser(InviteUser $inviteUser): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(RoleName::class)],
        ]);

        $inviteUser->handle($validated['name'], $validated['email'], RoleName::from($validated['role']));

        $this->reset('name', 'email', 'role', 'showModal');
        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function toggleActive(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        if ($user->is(auth()->user())) {
            $this->addError('name', __('You cannot deactivate your own account.'));

            return;
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        Flux::toast(variant: 'success', text: $user->is_active ? __('User activated.') : __('User deactivated.'));
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function records()
    {
        $query = User::query()->with('roles');

        if ($this->search !== '') {
            $query->where(function (Builder $query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        return $this->applyListing($query, null)->paginate(10);
    }

    public function canInvite(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPermission(Permission::UsersManage);
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'created_at'];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Users')" :subtitle="__('Create an account, assign one role, and send a set-password link.')">
        <x-slot:actions>
            @if ($this->canInvite())
                <flux:button variant="primary" icon="plus" wire:click="$set('showModal', true)">
                    {{ __('Invite user') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-data-table :records="$this->records" :status="$status">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                @if ($this->canInvite())
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->records as $user)
                    <flux:table.row wire:key="user-{{ $user->id }}">
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->roles->first()?->label ?? __('No role') }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-pill :active="$user->is_active" />
                        </flux:table.cell>
                        @if ($this->canInvite())
                            <flux:table.cell>
                                <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $user->id }})">
                                    {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>

    <x-form-modal :title="__('Invite user')" submit="createUser">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" />
            <flux:error name="name" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input type="email" wire:model="email" />
            <flux:error name="email" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Role') }}</flux:label>
            <flux:select wire:model="role" placeholder="{{ __('Choose a role') }}">
                @foreach (RoleName::cases() as $roleOption)
                    <flux:select.option :value="$roleOption->value" wire:key="role-option-{{ $roleOption->value }}">
                        {{ $roleOption->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="role" />
        </flux:field>
    </x-form-modal>
</div>
