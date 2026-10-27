<?php

use App\Enums\OwnerChangeType;
use App\Livewire\OwnerPortalComponent;
use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\Owner;
use App\Models\Vehicle;
use App\Services\OwnerChangeRequestService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;

new #[Title('My Property')] class extends OwnerPortalComponent
{
    use WithFileUploads;

    public string $tab = 'Household';
    public string $phone = '';
    public string $email = '';
    public string $mailingAddress = '';
    public string $name = '';
    public string $detail = '';
    public string $recordPhone = '';
    public string $recordEmail = '';
    public string $date = '';
    public string $recordType = '';
    public string $plateNo = '';
    public ?int $targetId = null;
    public string $form = '';
    public $attachment = null;

    public function mount(): void
    {
        $this->initializeOwnedProperty();
        $this->date = now()->toDateString();
        $this->ownedPropertySelected();
    }

    public function ownedPropertySelected(): void
    {
        unset($this->record, $this->linkedOwner);
        $this->phone = $this->linkedOwner?->phone ?? '';
        $this->email = $this->linkedOwner?->email ?? '';
        $this->mailingAddress = $this->linkedOwner?->mailing_address ?? '';
        $this->resetEditor();
    }

    #[Computed]
    public function record()
    {
        return $this->selectedProperty
            ? $this->ownerUser()->currentOwnedProperties()->with([
                'street', 'section', 'propertyType', 'currentResident',
                'residents', 'familyMembers', 'houseStaff', 'vehicles.stickers',
            ])->findOrFail($this->selectedProperty->id)
            : null;
    }

    #[Computed]
    public function linkedOwner(): ?Owner
    {
        return $this->selectedProperty
            ? $this->ownerUser()->currentOwnerRecords()->with('attachments')->where('property_id', $this->selectedProperty->id)->first()
            : null;
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, ['Household', 'Vehicles', 'Documents'], true)) {
            $this->tab = $tab;
            $this->resetEditor();
        }
    }

    public function submitContact(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty && $this->linkedOwner, 404);
        $data = $this->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'mailingAddress' => ['nullable', 'string', 'max:2000'],
        ]);
        $changes->submit($this->ownerUser(), $this->selectedProperty, OwnerChangeType::OwnerContact, [
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'mailing_address' => $data['mailingAddress'] ?: null,
        ], $this->linkedOwner);
        $this->saved();
    }

    public function edit(string $form, int $id): void
    {
        $record = $this->ownedRecord($form, $id);
        $this->form = $form;
        $this->targetId = $record->getKey();
        $this->name = (string) $record->getAttribute('name');
        $this->recordPhone = (string) ($record->getAttribute('phone') ?? '');
        $this->recordEmail = (string) ($record->getAttribute('email') ?? '');
        $this->detail = (string) ($record->getAttribute('relationship') ?? $record->getAttribute('role') ?? $record->getAttribute('driver_name') ?? '');
        $this->recordType = (string) ($record->getAttribute('occupancy_type') ?? $record->getAttribute('type') ?? '');
        $this->plateNo = (string) ($record->getAttribute('plate_no') ?? '');
    }

    public function submitResident(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'recordPhone' => ['nullable', 'string', 'max:30'],
            'recordEmail' => ['nullable', 'email', 'max:255'],
            'recordType' => ['required', Rule::in(['Owner-Occupied', 'Tenant', 'Caretaker', 'Relative'])],
            'date' => ['required', 'date'],
        ]);
        $target = $this->record?->currentResident;
        $changes->submit($this->ownerUser(), $this->selectedProperty, $target ? OwnerChangeType::ResidentUpdate : OwnerChangeType::ResidentMoveIn, [
            'name' => $data['name'], 'phone' => $data['recordPhone'] ?: null, 'email' => $data['recordEmail'] ?: null,
            'occupancy_type' => $data['recordType'], 'moved_in_on' => $data['date'],
        ], $target);
        $this->saved();
    }

    public function moveOut(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty && $this->record?->currentResident, 404);
        $changes->submit($this->ownerUser(), $this->selectedProperty, OwnerChangeType::ResidentMoveOut, ['moved_out_on' => now()->toDateString()], $this->record->currentResident);
        $this->saved();
    }

    public function submitFamily(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty && $this->record?->currentResident, 422);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'], 'detail' => ['required', 'string', 'max:100'],
            'recordPhone' => ['nullable', 'string', 'max:30'], 'recordEmail' => ['nullable', 'email', 'max:255'],
        ]);
        $target = $this->targetId ? $this->ownedRecord('family', $this->targetId) : null;
        $changes->submit($this->ownerUser(), $this->selectedProperty, $target ? OwnerChangeType::FamilyUpdate : OwnerChangeType::FamilyAdd, [
            'name' => $data['name'], 'relationship' => $data['detail'], 'phone' => $data['recordPhone'] ?: null, 'email' => $data['recordEmail'] ?: null,
        ], $target);
        $this->saved();
    }

    public function submitStaff(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'], 'detail' => ['required', 'string', 'max:100'],
            'recordPhone' => ['nullable', 'string', 'max:30'], 'recordType' => ['required', 'string', 'max:100'],
            'recordEmail' => ['required', 'string', 'max:255'],
        ]);
        $target = $this->targetId ? $this->ownedRecord('staff', $this->targetId) : null;
        $changes->submit($this->ownerUser(), $this->selectedProperty, $target ? OwnerChangeType::HouseStaffUpdate : OwnerChangeType::HouseStaffAdd, [
            'name' => $data['name'], 'role' => $data['detail'], 'phone' => $data['recordPhone'] ?: null,
            'id_type' => $data['recordType'], 'id_reference' => $data['recordEmail'], 'started_on' => now()->toDateString(),
        ], $target);
        $this->saved();
    }

    public function submitVehicle(OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'plateNo' => ['required', 'string', 'max:20'], 'detail' => ['required', 'string', 'max:255'],
            'recordType' => ['required', Rule::in(['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle'])],
            'name' => ['nullable', 'string', 'max:100'], 'recordPhone' => ['nullable', 'string', 'max:100'], 'recordEmail' => ['nullable', 'string', 'max:100'],
        ]);
        $target = $this->targetId ? $this->ownedRecord('vehicle', $this->targetId) : null;
        $changes->submit($this->ownerUser(), $this->selectedProperty, $target ? OwnerChangeType::VehicleUpdate : OwnerChangeType::VehicleAdd, [
            'plate_no' => $data['plateNo'], 'driver_name' => $data['detail'], 'type' => $data['recordType'],
            'make' => $data['name'] ?: null, 'model' => $data['recordPhone'] ?: null, 'color' => $data['recordEmail'] ?: null,
        ], $target);
        $this->saved();
    }

    public function deactivate(string $form, int $id, OwnerChangeRequestService $changes): void
    {
        abort_unless($this->selectedProperty, 404);
        $type = match ($form) {
            'family' => OwnerChangeType::FamilyRemove,
            'staff' => OwnerChangeType::HouseStaffDeactivate,
            'vehicle' => OwnerChangeType::VehicleDeactivate,
            default => abort(404),
        };
        $changes->submit($this->ownerUser(), $this->selectedProperty, $type, [], $this->ownedRecord($form, $id));
        $this->saved();
    }

    public function uploadDocument(): void
    {
        abort_unless($this->selectedProperty && $this->linkedOwner, 404);
        $this->validate(['attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $path = $this->attachment->store('hoa-documents/'.$this->selectedProperty->id, 'local');
        $this->linkedOwner->attachments()->create([
            'uploaded_by' => auth()->id(), 'disk' => 'local', 'path' => $path,
            'original_name' => $this->attachment->getClientOriginalName(),
            'mime_type' => $this->attachment->getMimeType(), 'size' => $this->attachment->getSize(),
        ]);
        $this->reset('attachment');
        unset($this->linkedOwner);
        Flux::toast(variant: 'success', text: __('Document uploaded.'));
    }

    private function ownedRecord(string $form, int $id): Model
    {
        abort_unless($this->record, 404);

        return match ($form) {
            'family' => $this->record->familyMembers()->findOrFail($id),
            'staff' => $this->record->houseStaff()->findOrFail($id),
            'vehicle' => $this->record->vehicles()->findOrFail($id),
            default => abort(404),
        };
    }

    private function saved(): void
    {
        $this->resetEditor();
        Flux::toast(variant: 'success', text: __('Change submitted for staff review.'));
    }

    private function resetEditor(): void
    {
        $this->reset(['name', 'detail', 'recordPhone', 'recordEmail', 'recordType', 'plateNo', 'targetId', 'form']);
        $this->date = now()->toDateString();
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Property')" :subtitle="__('Review your household and submit changes for staff approval.')" />
    @if (! $this->record)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}">{{ __('Contact the HOA office to review your account link.') }}</flux:callout>
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card><flux:text>{{ __('Property') }}</flux:text><flux:heading size="lg">{{ $this->record->code }}</flux:heading><flux:text>Block {{ $this->record->block }}, Lot {{ $this->record->lot }}</flux:text></flux:card>
            <flux:card><flux:text>{{ __('Area') }}</flux:text><flux:heading size="lg">{{ $this->record->lot_area }} sqm</flux:heading><flux:text>{{ $this->record->propertyType?->name }}</flux:text></flux:card>
            <flux:card><flux:text>{{ __('Address') }}</flux:text><flux:heading size="lg">{{ $this->record->house_no ?: '—' }}</flux:heading><flux:text>{{ $this->record->street?->name }} · {{ $this->record->section?->name }}</flux:text></flux:card>
        </div>
        <flux:card><form wire:submit="submitContact" class="grid gap-3 sm:grid-cols-2"><div class="sm:col-span-2"><flux:heading size="lg">{{ __('Owner contact details') }}</flux:heading></div><flux:input label="{{ __('Phone') }}" wire:model="phone" /><flux:input label="{{ __('Email') }}" type="email" wire:model="email" /><flux:textarea class="sm:col-span-2" label="{{ __('Mailing address') }}" wire:model="mailingAddress" /><div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Submit contact update') }}</flux:button></div></form></flux:card>
        <div class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">@foreach (['Household', 'Vehicles', 'Documents'] as $section)<flux:button wire:key="owner-property-tab-{{ $section }}" size="sm" :variant="$tab === $section ? 'primary' : 'ghost'" wire:click="showTab('{{ $section }}')">{{ $section }}</flux:button>@endforeach</div>

        @if ($tab === 'Household')
            <div class="grid gap-6 xl:grid-cols-2">
                <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Resident') }}</flux:heading><flux:text>{{ $this->record->currentResident?->name ?? __('No current resident') }}</flux:text><form wire:submit="submitResident" class="grid gap-3 sm:grid-cols-2"><flux:input label="{{ __('Name') }}" wire:model="name" /><flux:input label="{{ __('Phone') }}" wire:model="recordPhone" /><flux:input label="{{ __('Email') }}" type="email" wire:model="recordEmail" /><flux:select label="{{ __('Occupancy') }}" wire:model="recordType"><flux:select.option value="Owner-Occupied">Owner-Occupied</flux:select.option><flux:select.option value="Tenant">Tenant</flux:select.option><flux:select.option value="Caretaker">Caretaker</flux:select.option><flux:select.option value="Relative">Relative</flux:select.option></flux:select><flux:input label="{{ __('Effective date') }}" type="date" wire:model="date" /><div class="flex items-end gap-2"><flux:button type="submit" variant="primary">{{ __('Submit') }}</flux:button>@if ($this->record->currentResident)<flux:button type="button" wire:click="moveOut">{{ __('Move out') }}</flux:button>@endif</div></form></flux:card>
                <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Family members') }}</flux:heading>@foreach ($this->record->familyMembers->where('status', 'Active') as $member)<div wire:key="owner-family-{{ $member->id }}" class="flex justify-between gap-2 border-t py-2"><span>{{ $member->name }} · {{ $member->relationship }}</span><span><flux:button size="sm" variant="ghost" wire:click="edit('family', {{ $member->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="ghost" wire:click="deactivate('family', {{ $member->id }})">{{ __('Remove') }}</flux:button></span></div>@endforeach<form wire:submit="submitFamily" class="grid gap-3 sm:grid-cols-2"><flux:input label="{{ __('Name') }}" wire:model="name" /><flux:input label="{{ __('Relationship') }}" wire:model="detail" /><flux:input label="{{ __('Phone') }}" wire:model="recordPhone" /><flux:input label="{{ __('Email') }}" type="email" wire:model="recordEmail" /><div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Submit family change') }}</flux:button></div></form></flux:card>
                <flux:card class="space-y-3 xl:col-span-2"><flux:heading size="lg">{{ __('House staff') }}</flux:heading>@foreach ($this->record->houseStaff->where('status', 'Active') as $staff)<div wire:key="owner-staff-{{ $staff->id }}" class="flex justify-between gap-2 border-t py-2"><span>{{ $staff->name }} · {{ $staff->role }} · Pass {{ $staff->pass_no }}</span><span><flux:button size="sm" variant="ghost" wire:click="edit('staff', {{ $staff->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="ghost" wire:click="deactivate('staff', {{ $staff->id }})">{{ __('Deactivate') }}</flux:button></span></div>@endforeach<form wire:submit="submitStaff" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><flux:input label="{{ __('Name') }}" wire:model="name" /><flux:input label="{{ __('Role') }}" wire:model="detail" /><flux:input label="{{ __('Phone') }}" wire:model="recordPhone" /><flux:input label="{{ __('ID type') }}" wire:model="recordType" /><flux:input label="{{ __('ID reference') }}" wire:model="recordEmail" /><div class="flex items-end"><flux:button type="submit" variant="primary">{{ __('Submit staff change') }}</flux:button></div></form></flux:card>
            </div>
        @elseif ($tab === 'Vehicles')
            <div class="grid gap-6 lg:grid-cols-2"><flux:card class="space-y-3"><flux:heading size="lg">{{ __('Vehicles and stickers') }}</flux:heading>@foreach ($this->record->vehicles->where('status', 'Active') as $vehicle)<div wire:key="owner-vehicle-{{ $vehicle->id }}" class="border-t py-3"><div class="flex justify-between gap-2"><strong>{{ $vehicle->plate_no }} · {{ $vehicle->type }}</strong><span><flux:button size="sm" variant="ghost" wire:click="edit('vehicle', {{ $vehicle->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="ghost" wire:click="deactivate('vehicle', {{ $vehicle->id }})">{{ __('Deactivate') }}</flux:button></span></div><flux:text>{{ $vehicle->driver_name }}</flux:text>@foreach ($vehicle->stickers as $sticker)<flux:text class="text-xs">Sticker {{ $sticker->sticker_no }} ({{ $sticker->year }}) · {{ $sticker->status }}</flux:text>@endforeach</div>@endforeach</flux:card><flux:card><form wire:submit="submitVehicle" class="grid gap-3 sm:grid-cols-2"><div class="sm:col-span-2"><flux:heading size="lg">{{ __('Vehicle change') }}</flux:heading></div><flux:input label="{{ __('Plate number') }}" wire:model="plateNo" /><flux:input label="{{ __('Driver') }}" wire:model="detail" /><flux:select label="{{ __('Type') }}" wire:model="recordType"><flux:select.option value="Car">Car</flux:select.option><flux:select.option value="SUV">SUV</flux:select.option><flux:select.option value="Van">Van</flux:select.option><flux:select.option value="Pickup">Pickup</flux:select.option><flux:select.option value="Motorcycle">Motorcycle</flux:select.option></flux:select><flux:input label="{{ __('Make') }}" wire:model="name" /><flux:input label="{{ __('Model') }}" wire:model="recordPhone" /><flux:input label="{{ __('Color') }}" wire:model="recordEmail" /><div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Submit for review') }}</flux:button></div></form></flux:card></div>
        @else
            <flux:card class="space-y-4"><div><flux:heading size="lg">{{ __('Private owner documents') }}</flux:heading><flux:text>{{ __('PDF, JPEG, or PNG up to 10 MB. Only you and authorized office staff can access these files.') }}</flux:text></div><form wire:submit="uploadDocument" class="flex flex-wrap items-end gap-3"><flux:input label="{{ __('Document') }}" type="file" wire:model="attachment" accept=".pdf,.jpg,.jpeg,.png" /><flux:button type="submit" variant="primary">{{ __('Upload') }}</flux:button></form>@forelse ($this->linkedOwner?->attachments ?? [] as $document)<div wire:key="owner-document-{{ $document->id }}" class="border-t py-2"><a class="text-emerald-700 hover:underline dark:text-emerald-400" href="{{ route('attachments.download', $document) }}">{{ $document->original_name }}</a></div>@empty<flux:text>{{ __('No private documents uploaded.') }}</flux:text>@endforelse</flux:card>
        @endif
    @endif
</div>