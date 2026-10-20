<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Section;
use App\Models\Street;
use App\Services\PropertyRegistry;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Properties')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'All';
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $block = '';
    public string $lot = '';
    public string $houseNo = '';
    public string $streetId = '';
    public string $sectionId = '';
    public string $propertyTypeId = '';
    public string $lotArea = '';
    public string $propertyStatus = 'Vacant';
    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Property::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function properties()
    {
        return Property::query()
            ->with(auth()->user()->hasPermission(App\Enums\Permission::OwnersView)
                ? ['street', 'section', 'currentOwner']
                : ['street', 'section'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('block', 'like', '%'.$this->search.'%')
                        ->orWhere('lot', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== 'All', fn ($query) => $query->where('status', $this->status))
            ->orderBy('block')->orderBy('lot')->paginate(15);
    }

    #[Computed]
    public function sections()
    {
        return Section::query()->orderBy('name')->get();
    }

    #[Computed]
    public function streets()
    {
        return Street::query()->orderBy('name')->get();
    }

    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::query()->orderBy('name')->get();
    }

    public function createRecord(): void
    {
        $this->authorize('create', Property::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $property = Property::query()->findOrFail($id);
        $this->authorize('update', $property);
        $this->editingId = $property->id;
        $this->block = (string) $property->block;
        $this->lot = (string) $property->lot;
        $this->houseNo = (string) $property->house_no;
        $this->streetId = (string) $property->street_id;
        $this->sectionId = (string) $property->section_id;
        $this->propertyTypeId = (string) $property->property_type_id;
        $this->lotArea = (string) $property->lot_area;
        $this->propertyStatus = $property->status;
        $this->notes = (string) $property->notes;
        $this->showModal = true;
    }

    public function save(PropertyRegistry $registry): void
    {
        $property = $this->editingId ? Property::query()->findOrFail($this->editingId) : null;
        $this->authorize($property ? 'update' : 'create', $property ?? Property::class);

        $validated = $this->validate([
            'block' => ['required', 'integer', 'min:1'],
            'lot' => ['required', 'integer', 'min:1', Rule::unique('properties', 'lot')->where('block', $this->block)->ignore($property?->id)],
            'houseNo' => ['nullable', 'string', 'max:50'],
            'streetId' => ['nullable', 'integer', 'exists:streets,id'],
            'sectionId' => ['nullable', 'integer', 'exists:sections,id'],
            'propertyTypeId' => ['nullable', 'integer', 'exists:property_types,id'],
            'lotArea' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'propertyStatus' => ['required', Rule::in(['Occupied', 'Vacant', 'Under Construction', 'For Sale'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $attributes = [
            'block' => $validated['block'],
            'lot' => $validated['lot'],
            'house_no' => $validated['houseNo'] ?: null,
            'street_id' => $validated['streetId'] ?: null,
            'section_id' => $validated['sectionId'] ?: null,
            'property_type_id' => $validated['propertyTypeId'] ?: null,
            'lot_area' => $validated['lotArea'],
            'status' => $validated['propertyStatus'],
            'notes' => $validated['notes'] ?: null,
        ];

        if ($property) {
            $property->update($attributes + ['updated_by' => auth()->id()]);
        } else {
            $registry->createProperty($attributes, auth()->id());
        }

        $this->showModal = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: __('Property saved.'));
    }

    public function archive(int $id): void
    {
        $property = Property::query()->findOrFail($id);
        $this->authorize('delete', $property);
        $property->delete();
        Flux::toast(variant: 'success', text: __('Property archived.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'block', 'lot', 'houseNo', 'streetId', 'sectionId', 'propertyTypeId', 'lotArea', 'notes']);
        $this->propertyStatus = 'Vacant';
        $this->resetValidation();
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Properties')" :subtitle="__('One record for each block and lot.')">
        <x-slot:actions>
            @can('create', App\Models\Property::class)
                <flux:button variant="primary" wire:click="createRecord">{{ __('Add property') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Find code, block or lot" class="max-w-xs" />
        <select wire:model.live="status" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
            @foreach (['All', 'Occupied', 'Vacant', 'Under Construction', 'For Sale'] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                <tr><th class="p-3">Property</th><th class="p-3">Location</th><th class="p-3">Current owner</th><th class="p-3">Area</th><th class="p-3">Status</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($this->properties as $property)
                    <tr wire:key="property-{{ $property->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="p-3"><a class="font-medium text-emerald-700 hover:underline dark:text-emerald-400" href="{{ route('properties.show', $property) }}">{{ $property->code }}</a><div>Block {{ $property->block }} · Lot {{ $property->lot }}</div></td>
                        <td class="p-3">{{ $property->street?->name ?? '—' }}<div class="text-zinc-500">{{ $property->section?->name }}</div></td>
                        <td class="p-3">@if (auth()->user()->hasPermission(App\Enums\Permission::OwnersView)) {{ $property->currentOwner?->name ?? '—' }} @else — @endif</td>
                        <td class="p-3">{{ number_format((float) $property->lot_area, 2) }} sqm</td>
                        <td class="p-3"><x-status-pill :label="$property->status" /></td>
                        <td class="p-3">
                            @can('update', $property)
                                <flux:button size="sm" wire:click="edit({{ $property->id }})">Edit</flux:button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-zinc-500">No properties found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->properties->links() }}

    <flux:modal wire:model.self="showModal" class="max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit property' : 'Add property' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input label="Block" type="number" min="1" wire:model="block" />
                <flux:input label="Lot" type="number" min="1" wire:model="lot" />
                <flux:input label="House number" wire:model="houseNo" />
                <flux:input label="Lot area (sqm)" type="number" step="0.01" min="0.01" wire:model="lotArea" />
                <label class="flex flex-col gap-1 text-sm">Section
                    <select wire:model="sectionId" class="rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select section</option>@foreach ($this->sections as $section)<option value="{{ $section->id }}">{{ $section->name }}</option>@endforeach</select>
                </label>
                <label class="flex flex-col gap-1 text-sm">Street
                    <select wire:model="streetId" class="rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select street</option>@foreach ($this->streets as $street)<option value="{{ $street->id }}">{{ $street->name }}</option>@endforeach</select>
                </label>
                <label class="flex flex-col gap-1 text-sm">Type
                    <select wire:model="propertyTypeId" class="rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select type</option>@foreach ($this->propertyTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select>
                </label>
                <label class="flex flex-col gap-1 text-sm">Status
                    <select wire:model="propertyStatus" class="rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Occupied', 'Vacant', 'Under Construction', 'For Sale'] as $option)<option>{{ $option }}</option>@endforeach</select>
                </label>
            </div>
            <flux:textarea label="Notes" wire:model="notes" />
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="$set('showModal', false)">Cancel</flux:button><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
    </flux:modal>
</div>
