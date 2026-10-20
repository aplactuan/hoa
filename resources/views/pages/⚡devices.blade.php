<?php

use App\Enums\DeviceKind;
use App\Livewire\DataTableComponent;
use App\Models\Device;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Devices')] class extends DataTableComponent
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $kind = 'pc';

    public string $location = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Device::class);
    }

    public function createDevice(): void
    {
        $this->authorize('create', Device::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $device = Device::query()->findOrFail($id);
        $this->authorize('update', $device);
        $this->editingId = $device->id;
        $this->name = $device->name;
        $this->kind = $device->kind->value;
        $this->location = (string) $device->location;
        $this->isActive = $device->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', \Illuminate\Validation\Rule::enum(DeviceKind::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ]);

        $device = $this->editingId
            ? Device::query()->findOrFail($this->editingId)
            : new Device;

        $this->authorize($this->editingId ? 'update' : 'create', $this->editingId ? $device : Device::class);

        $device->fill([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'location' => $validated['location'] ?: null,
            'is_active' => (bool) $validated['isActive'],
        ])->save();

        $this->showModal = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: __('Device saved.'));
    }

    public function delete(int $id): void
    {
        $device = Device::query()->findOrFail($id);
        $this->authorize('delete', $device);
        $device->delete();
        Flux::toast(variant: 'success', text: __('Device deleted.'));
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Device>
     */
    #[Computed]
    public function records()
    {
        return $this->applyListing(Device::query())->paginate(10);
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->kind = DeviceKind::Pc->value;
        $this->location = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'kind', 'location'];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Devices')" :subtitle="__('Name the office PCs and the guardhouse tablet so the audit log can label them.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createDevice">{{ __('Add device') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-data-table :records="$this->records" :status="$status">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kind'" :direction="$sortDirection" wire:click="sort('kind')">{{ __('Kind') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->records as $device)
                    <flux:table.row wire:key="device-row-{{ $device->id }}">
                        <flux:table.cell>{{ $device->name }}</flux:table.cell>
                        <flux:table.cell>{{ $device->kind->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $device->location ?: '—' }}</flux:table.cell>
                        <flux:table.cell><x-status-pill :active="$device->is_active" /></flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $device->id }})">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="delete({{ $device->id }})">{{ __('Delete') }}</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>

    <x-form-modal :title="$editingId ? __('Edit device') : __('Add device')">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" />
            <flux:error name="name" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Kind') }}</flux:label>
            <flux:select wire:model="kind">
                @foreach (DeviceKind::cases() as $kindOption)
                    <flux:select.option :value="$kindOption->value" wire:key="kind-{{ $kindOption->value }}">{{ $kindOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="kind" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Location') }}</flux:label>
            <flux:input wire:model="location" />
            <flux:error name="location" />
        </flux:field>
        <flux:switch wire:model="isActive" :label="__('Active')" />
    </x-form-modal>
</div>
