<?php

use App\Models\AuditLog;
use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Vehicle;
use App\Enums\Permission;
use App\Services\AccountService;
use App\Services\DisplayCode;
use App\Services\PropertyRegistry;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Property profile')] class extends Component
{
    use WithFileUploads;

    public Property $property;
    public string $tab = 'Overview';
    public string $ownerName = '';
    public string $ownerPhone = '';
    public string $ownerEmail = '';
    public string $ownerType = 'Sole Owner';
    public string $ownerStartedOn = '';
    public string $residentName = '';
    public string $residentType = 'Owner-Occupied';
    public string $residentMovedInOn = '';
    public string $moveOutDate = '';
    public string $familyName = '';
    public string $familyRelationship = '';
    public string $staffName = '';
    public string $staffRole = '';
    public string $staffIdType = '';
    public string $staffIdReference = '';
    public string $staffPassNo = '';
    public string $renewStaffId = '';
    public string $renewPassExpiresOn = '';
    public string $staffPassExpiresOn = '';
    public string $plateNo = '';
    public string $driverName = '';
    public string $vehicleType = 'Car';
    public string $stickerNo = '';
    public int $stickerYear = 0;
    public ?int $stickerVehicleId = null;
    public $attachment = null;
    public string $uploadTarget = '';

    public function mount(Property $property): void
    {
        $this->authorize('view', $property);
        $this->property = $property;
        $this->ownerStartedOn = now()->toDateString();
        $this->residentMovedInOn = now()->toDateString();
        $this->moveOutDate = now()->toDateString();
        $this->staffPassExpiresOn = now()->endOfYear()->toDateString();
        $this->stickerYear = now()->year;
    }

    #[Computed]
    public function record(): Property
    {
        $relations = ['street', 'section', 'propertyType', 'currentResident', 'residents', 'familyMembers', 'houseStaff', 'vehicles.stickers'];

        if (auth()->user()->hasPermission(Permission::OwnersView)) {
            $relations[] = 'currentOwner.attachments';
            $relations['owners'] = fn ($query) => $query->orderByDesc('started_on');
        }

        if (auth()->user()->hasRole(App\Enums\RoleName::Administrator) || auth()->user()->hasRole(App\Enums\RoleName::OfficeStaff)) {
            array_push($relations, 'currentResident.attachments', 'houseStaff.attachments');
        }

        if (auth()->user()->hasPermission(Permission::FinancialView)) {
            array_push($relations, 'statements', 'payments', 'charges');
        }

        if (auth()->user()->hasPermission(Permission::TransactionsView)) {
            array_push($relations, 'cases', 'violations', 'transactions', 'correspondence', 'reservations');
        }

        return Property::query()->with($relations)->findOrFail($this->property->id);
    }

    #[Computed]
    public function account(): array
    {
        abort_unless(auth()->user()->hasPermission(Permission::FinancialView), 403);

        return app(AccountService::class)->summary($this->record);
    }

    #[Computed]
    public function auditEntries()
    {
        return AuditLog::query()->where('property_id', $this->property->id)->latest()->limit(50)->get();
    }

    public function showTab(string $tab): void
    {
        $allowed = ['Overview', 'Household', 'Vehicles'];

        if (auth()->user()->hasPermission(Permission::OwnersView)) {
            $allowed[] = 'Ownership';
        }

        if (auth()->user()->hasPermission(Permission::FinancialView)) {
            $allowed[] = 'Billing';
        }

        if (auth()->user()->hasPermission(Permission::TransactionsView)) {
            $allowed[] = 'Office';
        }

        if (auth()->user()->hasPermission(Permission::AuditView)) {
            $allowed[] = 'Audit trail';
        }

        if (in_array($tab, $allowed, true)) {
            $this->tab = $tab;
        }
    }

    public function saveOwner(PropertyRegistry $registry): void
    {
        $this->authorize('create', Owner::class);
        $data = $this->validate([
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerPhone' => ['nullable', 'string', 'max:30'],
            'ownerEmail' => ['nullable', 'email', 'max:255'],
            'ownerType' => ['required', Rule::in(['Sole Owner', 'Co-Owner', 'Corporate', 'Heirs/Estate'])],
            'ownerStartedOn' => ['required', 'date'],
        ]);
        $registry->transferOwnership($this->record, [
            'name' => $data['ownerName'],
            'phone' => $data['ownerPhone'] ?: null,
            'email' => $data['ownerEmail'] ?: null,
            'ownership_type' => $data['ownerType'],
            'started_on' => $data['ownerStartedOn'],
        ], auth()->id());
        $this->reset(['ownerName', 'ownerPhone', 'ownerEmail']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Ownership recorded.'));
    }

    public function saveResident(PropertyRegistry $registry): void
    {
        $this->authorize('create', Resident::class);
        $data = $this->validate([
            'residentName' => ['required', 'string', 'max:255'],
            'residentType' => ['required', Rule::in(['Owner-Occupied', 'Tenant', 'Caretaker', 'Relative'])],
            'residentMovedInOn' => ['required', 'date'],
        ]);
        $registry->moveIn($this->record, [
            'name' => $data['residentName'],
            'occupancy_type' => $data['residentType'],
            'moved_in_on' => $data['residentMovedInOn'],
        ], auth()->id());
        $this->reset('residentName');
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Resident moved in.'));
    }

    public function moveOut(PropertyRegistry $registry): void
    {
        $resident = $this->record->currentResident;
        abort_unless($resident, 404);
        $this->authorize('update', $resident);
        $this->validate(['moveOutDate' => ['required', 'date']]);
        $registry->moveOut($resident, $this->moveOutDate, auth()->id());
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Resident moved out.'));
    }

    public function saveFamily(DisplayCode $codes): void
    {
        $this->authorize('create', FamilyMember::class);
        $resident = $this->record->currentResident;
        abort_unless($resident, 422);
        $data = $this->validate([
            'familyName' => ['required', 'string', 'max:255'],
            'familyRelationship' => ['required', 'string', 'max:100'],
        ]);
        $this->record->familyMembers()->create([
            'code' => $codes->next('family_member'),
            'resident_id' => $resident->id,
            'name' => $data['familyName'],
            'relationship' => $data['familyRelationship'],
            'status' => 'Active',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->reset(['familyName', 'familyRelationship']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Family member added.'));
    }

    public function saveStaff(DisplayCode $codes): void
    {
        $this->authorize('create', HouseStaff::class);
        $data = $this->validate([
            'staffName' => ['required', 'string', 'max:255'],
            'staffRole' => ['required', 'string', 'max:100'],
            'staffIdType' => ['required', 'string', 'max:100'],
            'staffIdReference' => ['required', 'string', 'max:255'],
            'staffPassNo' => ['required', 'string', 'max:100', 'unique:house_staff,pass_no'],
            'staffPassExpiresOn' => ['required', 'date', 'after:today'],
        ]);
        $this->record->houseStaff()->create([
            'code' => $codes->next('house_staff'),
            'resident_id' => $this->record->current_resident_id,
            'name' => $data['staffName'],
            'role' => $data['staffRole'],
            'id_type' => $data['staffIdType'],
            'id_reference' => $data['staffIdReference'],
            'pass_no' => $data['staffPassNo'],
            'pass_expires_on' => $data['staffPassExpiresOn'],
            'started_on' => now()->toDateString(),
            'status' => 'Active',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->reset(['staffName', 'staffRole', 'staffIdType', 'staffIdReference', 'staffPassNo']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('House staff added.'));
    }

    public function renewStaffPass(): void
    {
        abort_unless(auth()->user()->hasRole(App\Enums\RoleName::Administrator) || auth()->user()->hasRole(App\Enums\RoleName::OfficeStaff), 403);
        $data = $this->validate([
            'renewStaffId' => ['required', 'integer'],
            'renewPassExpiresOn' => ['required', 'date', 'after:today'],
        ]);
        $staff = $this->record->houseStaff()->where('status', 'Active')->findOrFail($data['renewStaffId']);
        $staff->update(['pass_expires_on' => $data['renewPassExpiresOn'], 'updated_by' => auth()->id()]);
        $this->reset(['renewStaffId', 'renewPassExpiresOn']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Staff pass renewed.'));
    }

    public function saveVehicle(PropertyRegistry $registry): void
    {
        $this->authorize('create', Vehicle::class);
        $data = $this->validate([
            'plateNo' => ['required', 'string', 'max:20'],
            'driverName' => ['required', 'string', 'max:255'],
            'vehicleType' => ['required', Rule::in(['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle'])],
        ]);
        $registry->registerVehicle($this->record, [
            'plate_no' => $data['plateNo'],
            'driver_name' => $data['driverName'],
            'type' => $data['vehicleType'],
        ], auth()->id());
        $this->reset(['plateNo', 'driverName']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Vehicle registered.'));
    }

    public function issueSticker(PropertyRegistry $registry): void
    {
        $this->authorize('create', Vehicle::class);
        $data = $this->validate([
            'stickerVehicleId' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('property_id', $this->property->id)],
            'stickerNo' => ['required', 'string', 'max:100', 'unique:vehicle_stickers,sticker_no'],
            'stickerYear' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $vehicle = $this->record->vehicles->firstWhere('id', (int) $data['stickerVehicleId']);
        abort_unless($vehicle, 404);
        $registry->issueSticker($vehicle, $data['stickerNo'], (int) $data['stickerYear'], auth()->id());
        $this->reset(['stickerVehicleId', 'stickerNo']);
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Sticker issued.'));
    }

    public function uploadDocument(): void
    {
        abort_unless(auth()->user()->hasRole(App\Enums\RoleName::Administrator) || auth()->user()->hasRole(App\Enums\RoleName::OfficeStaff), 403);
        $this->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'uploadTarget' => ['required', 'string', 'max:50'],
        ]);
        $record = match (true) {
            $this->uploadTarget === 'owner' => $this->record->currentOwner,
            $this->uploadTarget === 'resident' => $this->record->currentResident,
            str_starts_with($this->uploadTarget, 'staff:') => $this->record->houseStaff->firstWhere('id', (int) substr($this->uploadTarget, 6)),
            default => null,
        };
        abort_unless($record, 404);
        $path = $this->attachment->store('hoa-documents/'.$this->property->id, 'local');
        $record->attachments()->create([
            'uploaded_by' => auth()->id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->attachment->getClientOriginalName(),
            'mime_type' => $this->attachment->getMimeType(),
            'size' => $this->attachment->getSize(),
        ]);
        $this->reset('attachment');
        unset($this->record);
        Flux::toast(variant: 'success', text: __('Document attached.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="'Block '.$this->record->block.' · Lot '.$this->record->lot" :subtitle="$this->record->code.' · '.$this->record->account_no" />

    <div class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
        @foreach (['Overview', 'Ownership', 'Household', 'Vehicles', 'Billing', 'Office', 'Audit trail'] as $section)
            @if (($section === 'Ownership' && auth()->user()->hasPermission(Permission::OwnersView)) || ($section === 'Billing' && auth()->user()->hasPermission(Permission::FinancialView)) || ($section === 'Office' && auth()->user()->hasPermission(Permission::TransactionsView)) || ($section === 'Audit trail' && auth()->user()->hasPermission(Permission::AuditView)) || in_array($section, ['Overview', 'Household', 'Vehicles']))
                <flux:button wire:key="tab-{{ $section }}" size="sm" :variant="$tab === $section ? 'primary' : 'ghost'" wire:click="showTab('{{ $section }}')">{{ $section }}</flux:button>
            @endif
        @endforeach
    </div>

    @if ($tab === 'Overview')
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card><flux:heading size="lg">Property</flux:heading><flux:text>{{ $this->record->status }} · {{ $this->record->lot_area }} sqm</flux:text><flux:text>{{ $this->record->street?->name }} {{ $this->record->section?->name }}</flux:text></flux:card>
            @if (auth()->user()->hasPermission(Permission::OwnersView))
                <flux:card><flux:heading size="lg">Owner</flux:heading><flux:text>{{ $this->record->currentOwner?->name ?? 'No current owner' }}</flux:text></flux:card>
            @endif
            <flux:card><flux:heading size="lg">Resident</flux:heading><flux:text>{{ $this->record->currentResident?->name ?? 'No current resident' }}</flux:text></flux:card>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card>{{ $this->record->familyMembers->where('status', 'Active')->count() }} family members</flux:card>
            <flux:card>{{ $this->record->houseStaff->where('status', 'Active')->count() }} house staff</flux:card>
            <flux:card>{{ $this->record->vehicles->where('status', 'Active')->count() }} vehicles</flux:card>
        </div>
    @elseif ($tab === 'Ownership' && auth()->user()->hasPermission(Permission::OwnersView))
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-3"><flux:heading size="lg">Ownership history</flux:heading>
                @forelse ($this->record->owners as $owner)
                    <div wire:key="owner-{{ $owner->id }}" class="border-t border-zinc-200 py-3 dark:border-zinc-700"><strong>{{ $owner->name }}</strong> · {{ $owner->status }}<div class="text-sm text-zinc-500">{{ $owner->started_on->toDateString() }} – {{ $owner->ended_on?->toDateString() ?? 'Present' }}</div></div>
                @empty <flux:text>No owners recorded.</flux:text> @endforelse
            </flux:card>
            @can('create', App\Models\Owner::class)
                <flux:card><form wire:submit="saveOwner" class="flex flex-col gap-3"><flux:heading size="lg">Record owner or transfer</flux:heading><flux:input label="Name" wire:model="ownerName" /><flux:input label="Phone" wire:model="ownerPhone" /><flux:input label="Email" type="email" wire:model="ownerEmail" /><label class="text-sm">Ownership type<select wire:model="ownerType" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Sole Owner', 'Co-Owner', 'Corporate', 'Heirs/Estate'] as $type)<option>{{ $type }}</option>@endforeach</select></label><flux:input label="Start or transfer date" type="date" wire:model="ownerStartedOn" /><flux:button type="submit" variant="primary">Record ownership</flux:button></form></flux:card>
            @endcan
        </div>
    @elseif ($tab === 'Household')
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-3"><flux:heading size="lg">Residents</flux:heading>
                @foreach ($this->record->residents as $resident)<div wire:key="resident-{{ $resident->id }}">{{ $resident->name }} · {{ $resident->status }} · {{ $resident->occupancy_type }}</div>@endforeach
                @can('create', App\Models\Resident::class)
                    @if (! $this->record->currentResident)
                        <form wire:submit="saveResident" class="flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:input label="Resident name" wire:model="residentName" /><label class="text-sm">Occupancy<select wire:model="residentType" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Owner-Occupied', 'Tenant', 'Caretaker', 'Relative'] as $type)<option>{{ $type }}</option>@endforeach</select></label><flux:input label="Move-in date" type="date" wire:model="residentMovedInOn" /><flux:button type="submit" variant="primary">Move in</flux:button></form>
                    @else
                        <form wire:submit="moveOut" class="flex gap-2"><flux:input label="Move-out date" type="date" wire:model="moveOutDate" /><flux:button type="submit">Move out</flux:button></form>
                    @endif
                @endcan
            </flux:card>
            <flux:card class="flex flex-col gap-3"><flux:heading size="lg">Family members</flux:heading>
                @foreach ($this->record->familyMembers as $member)<div wire:key="family-{{ $member->id }}">{{ $member->name }} · {{ $member->relationship }} · {{ $member->status }}</div>@endforeach
                @if ($this->record->currentResident)
                    @can('create', App\Models\FamilyMember::class)
                        <form wire:submit="saveFamily" class="flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:input label="Name" wire:model="familyName" /><flux:input label="Relationship" wire:model="familyRelationship" /><flux:button type="submit" variant="primary">Add family member</flux:button></form>
                    @endcan
                @endif
            </flux:card>
            <flux:card class="flex flex-col gap-3"><flux:heading size="lg">House staff</flux:heading>
                @foreach ($this->record->houseStaff as $staff)<div wire:key="staff-{{ $staff->id }}">{{ $staff->name }} · {{ $staff->role }} · {{ $staff->status }} · Pass {{ $staff->pass_no }}</div>@endforeach
                @can('create', App\Models\HouseStaff::class)
                    <form wire:submit="saveStaff" class="flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:input label="Name" wire:model="staffName" /><flux:input label="Role" wire:model="staffRole" /><flux:input label="ID type" wire:model="staffIdType" /><flux:input label="ID reference" wire:model="staffIdReference" /><flux:input label="Pass number" wire:model="staffPassNo" /><flux:input label="Pass expiry" type="date" wire:model="staffPassExpiresOn" /><flux:button type="submit" variant="primary">Add staff</flux:button></form>
                    <form wire:submit="renewStaffPass" class="flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:heading>Renew staff pass</flux:heading><label class="text-sm">Staff<select wire:model="renewStaffId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->record->houseStaff->where('status', 'Active') as $staff)<option value="{{ $staff->id }}">{{ $staff->name }} · {{ $staff->pass_no }}</option>@endforeach</select></label><flux:input label="New expiry" type="date" wire:model="renewPassExpiresOn" /><flux:button type="submit">Renew pass</flux:button></form>
                @endcan
            </flux:card>
        </div>
        @if (auth()->user()->hasRole(App\Enums\RoleName::Administrator) || auth()->user()->hasRole(App\Enums\RoleName::OfficeStaff))
            <flux:card class="mt-4"><flux:heading size="lg">Private documents</flux:heading>
                <form wire:submit="uploadDocument" class="mt-3 flex flex-wrap items-end gap-3"><label class="text-sm">Attach to<select wire:model="uploadTarget" class="block rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select person</option>@if ($this->record->currentOwner)<option value="owner">Current owner</option>@endif @if ($this->record->currentResident)<option value="resident">Current resident</option>@endif @foreach ($this->record->houseStaff as $staff)<option value="staff:{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></label><flux:input label="PDF or image" type="file" wire:model="attachment" /><flux:button type="submit" variant="primary">Upload</flux:button></form>
                @foreach ([$this->record->currentOwner, $this->record->currentResident, ...$this->record->houseStaff] as $person)
                    @if ($person) @foreach ($person->attachments as $document)<div wire:key="document-{{ $document->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700"><a href="{{ route('attachments.download', $document) }}" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $document->original_name }}</a> · {{ class_basename($person) }}</div>@endforeach @endif
                @endforeach
            </flux:card>
        @endif
    @elseif ($tab === 'Vehicles')
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-3"><flux:heading size="lg">Vehicles and stickers</flux:heading>
                @foreach ($this->record->vehicles as $vehicle)<div wire:key="vehicle-{{ $vehicle->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700"><strong>{{ $vehicle->plate_no }}</strong> · {{ $vehicle->type }} · {{ $vehicle->driver_name }} @foreach ($vehicle->stickers as $sticker)<div class="text-sm text-zinc-500">Sticker {{ $sticker->sticker_no }} ({{ $sticker->year }}) · {{ $sticker->status }}</div>@endforeach</div>@endforeach
            </flux:card>
            @can('create', App\Models\Vehicle::class)
                <div class="flex flex-col gap-4"><flux:card><form wire:submit="saveVehicle" class="flex flex-col gap-3"><flux:heading size="lg">Register vehicle</flux:heading><flux:input label="Plate" wire:model="plateNo" /><flux:input label="Driver" wire:model="driverName" /><label class="text-sm">Type<select wire:model="vehicleType" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Car', 'SUV', 'Van', 'Pickup', 'Motorcycle'] as $type)<option>{{ $type }}</option>@endforeach</select></label><flux:button type="submit" variant="primary">Register</flux:button></form></flux:card>
                <flux:card><form wire:submit="issueSticker" class="flex flex-col gap-3"><flux:heading size="lg">Issue or renew sticker</flux:heading><label class="text-sm">Vehicle<select wire:model="stickerVehicleId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select vehicle</option>@foreach ($this->record->vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_no }}</option>@endforeach</select></label><flux:input label="Sticker number" wire:model="stickerNo" /><flux:input label="Year" type="number" wire:model="stickerYear" /><flux:button type="submit" variant="primary">Issue sticker</flux:button></form></flux:card></div>
            @endcan
        </div>
    @elseif ($tab === 'Audit trail' && auth()->user()->hasPermission(Permission::AuditView))
        <flux:card><flux:heading size="lg">Change history</flux:heading>
            @foreach ($this->auditEntries as $entry)<div wire:key="audit-{{ $entry->id }}" class="border-t border-zinc-200 py-3 text-sm dark:border-zinc-700">{{ $entry->created_at->format('M j, Y g:i A') }} · {{ $entry->action->label() }} · {{ class_basename($entry->auditable_type ?? '') }}</div>@endforeach
        </flux:card>
    @elseif ($tab === 'Billing' && auth()->user()->hasPermission(Permission::FinancialView))
        <div class="grid gap-4 sm:grid-cols-3"><flux:card><flux:text>Standing</flux:text><flux:heading size="lg">{{ $this->account['standing'] }}</flux:heading></flux:card><flux:card><flux:text>Outstanding</flux:text><flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->account['outstanding_cents']) }}</flux:heading></flux:card><flux:card><flux:text>Advance credit</flux:text><flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->account['credit_cents']) }}</flux:heading></flux:card></div>
        <div class="grid gap-4 lg:grid-cols-3">
            <flux:card><flux:heading size="lg">Statements</flux:heading>@foreach ($this->record->statements as $statement)<div wire:key="profile-statement-{{ $statement->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $statement->code }} · ₱{{ $statement->regular }}</div>@endforeach</flux:card>
            <flux:card><flux:heading size="lg">Payments</flux:heading>@foreach ($this->record->payments as $payment)<div wire:key="profile-payment-{{ $payment->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $payment->or_no }} · ₱{{ $payment->amount }} · {{ $payment->status }}</div>@endforeach</flux:card>
            <flux:card><flux:heading size="lg">Charges</flux:heading>@foreach ($this->record->charges as $charge)<div wire:key="profile-charge-{{ $charge->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $charge->description }} · ₱{{ $charge->amount }}</div>@endforeach</flux:card>
        </div>
    @elseif ($tab === 'Office' && auth()->user()->hasPermission(Permission::TransactionsView))
        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card><flux:heading size="lg">Transactions</flux:heading>@foreach ($this->record->transactions as $transaction)<div wire:key="profile-transaction-{{ $transaction->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $transaction->code }} · {{ $transaction->status }}</div>@endforeach</flux:card>
            <flux:card><flux:heading size="lg">Requests and complaints</flux:heading>@foreach ($this->record->cases as $case)<div wire:key="profile-case-{{ $case->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $case->code }} · {{ $case->status }}</div>@endforeach</flux:card>
            <flux:card><flux:heading size="lg">Violations</flux:heading>@foreach ($this->record->violations as $violation)<div wire:key="profile-violation-{{ $violation->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $violation->code }} · {{ $violation->status }}</div>@endforeach</flux:card>
            <flux:card><flux:heading size="lg">Correspondence and bookings</flux:heading>@foreach ($this->record->correspondence as $letter)<div wire:key="profile-letter-{{ $letter->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $letter->subject }} · {{ $letter->status }}</div>@endforeach @foreach ($this->record->reservations as $reservation)<div wire:key="profile-reservation-{{ $reservation->id }}" class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $reservation->code }} · {{ $reservation->approval_status }}</div>@endforeach</flux:card>
        </div>
    @endif
</div>
