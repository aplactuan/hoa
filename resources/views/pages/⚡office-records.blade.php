<?php

use App\Enums\RoleName;
use App\Models\Correspondence;
use App\Models\Facility;
use App\Models\FacilityReservation;
use App\Models\OfficeTransaction;
use App\Models\Property;
use App\Models\RequestCategory;
use App\Models\ServiceCase;
use App\Models\TransactionType;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Services\DisplayCode;
use App\Services\OfficeService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Office records')] class extends Component
{
    use WithPagination;

    public string $kind = '';
    public string $propertyId = '';
    public string $typeId = '';
    public string $name = '';
    public string $description = '';
    public string $priority = 'Medium';
    public string $eventOn = '';
    public string $startsAt = '';
    public string $endsAt = '';
    public string $guests = '0';
    public string $subject = '';
    public string $recipient = '';
    public string $correspondenceType = 'Letter';
    public ?int $selectedId = null;
    public string $actionNote = '';
    public string $refundReference = '';

    public function mount(): void
    {
        $route = request()->route()?->getName();
        $this->kind = match ($route) {
            'transactions.index' => 'Transactions',
            'requests.index' => 'Requests & Complaints',
            'violations.index' => 'Violations',
            'facilities.index' => 'Facility Use',
            'correspondence.index' => 'Correspondence',
            default => abort(404),
        };
        $this->eventOn = now()->addDay()->toDateString();
    }

    #[Computed]
    public function records()
    {
        $query = match ($this->kind) {
            'Transactions' => OfficeTransaction::query()->with(['property', 'transactionType', 'charge']),
            'Requests & Complaints' => ServiceCase::query()->with(['property', 'category']),
            'Violations' => Violation::query()->with(['property', 'violationType']),
            'Facility Use' => FacilityReservation::query()->with(['property', 'facility']),
            default => Correspondence::query()->with('property'),
        };

        if (auth()->user()->hasRole(RoleName::SecurityGuard)) {
            $query->where('created_by', auth()->id());
        }

        return $query->latest()->paginate(20);
    }

    #[Computed]
    public function properties()
    {
        return Property::query()->orderBy('block')->orderBy('lot')->get(['id', 'code', 'block', 'lot']);
    }

    #[Computed]
    public function types()
    {
        return match ($this->kind) {
            'Transactions' => TransactionType::query()->where('is_active', true)->orderBy('name')->get(),
            'Requests & Complaints' => RequestCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'Violations' => ViolationType::query()->where('is_active', true)->orderBy('name')->get(),
            'Facility Use' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    public function save(OfficeService $office, DisplayCode $codes): void
    {
        abort_unless($this->canCreate(), 403);
        $data = $this->validate([
            'propertyId' => ['required', 'integer', 'exists:properties,id'],
            'typeId' => [$this->kind === 'Correspondence' ? 'nullable' : 'required', 'integer'],
            'name' => [in_array($this->kind, ['Transactions', 'Requests & Complaints', 'Facility Use'], true) ? 'required' : 'nullable', 'string', 'max:255'],
            'description' => [$this->kind === 'Facility Use' || $this->kind === 'Correspondence' ? 'nullable' : 'required', 'string', 'max:5000'],
        ]);
        $property = Property::query()->findOrFail($data['propertyId']);
        $typeId = (int) $this->typeId;

        match ($this->kind) {
            'Transactions' => $office->createTransaction($property, TransactionType::query()->findOrFail($typeId), $this->name, auth()->id()),
            'Requests & Complaints' => $office->logCase($property, RequestCategory::query()->findOrFail($typeId), $this->name, $this->description, $this->priority, auth()->id()),
            'Violations' => $office->recordViolation($property, ViolationType::query()->findOrFail($typeId), $this->description, auth()->id()),
            'Facility Use' => $this->saveReservation($office, $property, $typeId),
            default => $this->saveCorrespondence($property, $codes),
        };

        $this->reset(['propertyId', 'typeId', 'name', 'description', 'subject', 'recipient']);
        unset($this->records);
        Flux::toast(variant: 'success', text: __('Record saved.'));
    }

    private function saveReservation(OfficeService $office, Property $property, int $facilityId): void
    {
        $this->validate([
            'eventOn' => ['required', 'date', 'after_or_equal:today'],
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i', 'after:startsAt'],
            'guests' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $office->bookFacility($property, Facility::query()->findOrFail($facilityId), $this->eventOn, $this->startsAt, $this->endsAt, $this->name, (int) $this->guests, auth()->id());
    }

    private function saveCorrespondence(Property $property, DisplayCode $codes): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'correspondenceType' => ['required', Rule::in(['Notice', 'Letter', 'Demand Letter', 'Memo', 'Email', 'Circular'])],
            'recipient' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(fn () => $property->correspondence()->create([
            'code' => $codes->next('correspondence'),
            'type' => $this->correspondenceType,
            'subject' => $this->subject,
            'sender' => auth()->user()->name,
            'recipient' => $this->recipient,
            'notes' => $this->description ?: null,
            'status' => 'Pending',
            'created_by' => auth()->id(),
        ]));
    }

    public function act(int $id, OfficeService $office): void
    {
        abort_unless($this->canManage(), 403);

        match ($this->kind) {
            'Transactions' => $office->completeTransaction(OfficeTransaction::query()->findOrFail($id), auth()->id()),
            'Requests & Complaints' => $office->resolveCase(ServiceCase::query()->findOrFail($id), $this->actionNote, auth()->id()),
            'Violations' => $office->issueNotice(Violation::query()->findOrFail($id), auth()->id()),
            'Facility Use' => $this->approve($id, $office),
            default => Correspondence::query()->findOrFail($id)->update(['status' => 'Acknowledged', 'acknowledged_on' => now()->toDateString()]),
        };

        $this->reset('actionNote');
        unset($this->records);
        Flux::toast(variant: 'success', text: __('Record updated.'));
    }

    private function approve(int $id, OfficeService $office): void
    {
        abort_unless(auth()->user()->hasRole(RoleName::Administrator), 403);
        $office->approveReservation(FacilityReservation::query()->findOrFail($id), auth()->id());
    }

    public function decline(int $id, OfficeService $office): void
    {
        abort_unless($this->kind === 'Facility Use' && auth()->user()->hasRole(RoleName::Administrator), 403);
        $office->declineReservation(FacilityReservation::query()->findOrFail($id), auth()->id());
        unset($this->records);
    }

    public function refund(int $id, OfficeService $office): void
    {
        abort_unless($this->kind === 'Facility Use' && (auth()->user()->hasRole(RoleName::Treasurer) || auth()->user()->hasRole(RoleName::Administrator)), 403);
        $this->validate(['refundReference' => ['required', 'string', 'max:100']]);
        $office->refundDeposit(FacilityReservation::query()->findOrFail($id), $this->refundReference, auth()->id());
        $this->reset('refundReference');
        unset($this->records);
    }

    public function canCreate(): bool
    {
        return auth()->user()->hasRole(RoleName::Administrator)
            || auth()->user()->hasRole(RoleName::OfficeStaff)
            || (in_array($this->kind, ['Transactions', 'Facility Use', 'Correspondence'], true) && auth()->user()->hasRole(RoleName::Treasurer))
            || ($this->kind === 'Requests & Complaints' && auth()->user()->hasRole(RoleName::SecurityGuard));
    }

    public function canManage(): bool
    {
        if ($this->kind === 'Facility Use') {
            return auth()->user()->hasRole(RoleName::Administrator);
        }

        return auth()->user()->hasRole(RoleName::Administrator)
            || auth()->user()->hasRole(RoleName::OfficeStaff)
            || (in_array($this->kind, ['Transactions', 'Correspondence'], true) && auth()->user()->hasRole(RoleName::Treasurer));
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header :title="$kind" subtitle="Records linked to a property." />
        @if ($this->canCreate())
            <flux:card><form wire:submit="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm">Property<select wire:model="propertyId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->properties as $property)<option value="{{ $property->id }}">{{ $property->code }} · Block {{ $property->block }} Lot {{ $property->lot }}</option>@endforeach</select></label>
                @if ($kind !== 'Correspondence')
                    <label class="text-sm">{{ $kind === 'Facility Use' ? 'Facility' : 'Type or category' }}<select wire:model="typeId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array($kind, ['Transactions', 'Requests & Complaints', 'Facility Use']))
                    <flux:input :label="$kind === 'Facility Use' ? 'Purpose' : 'Requested or filed by'" wire:model="name" />
                @endif
                @if ($kind === 'Correspondence')
                    <flux:input label="Subject" wire:model="subject" /><flux:input label="Recipient" wire:model="recipient" /><label class="text-sm">Type<select wire:model="correspondenceType" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Notice', 'Letter', 'Demand Letter', 'Memo', 'Email', 'Circular'] as $option)<option>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if ($kind === 'Requests & Complaints')
                    <label class="text-sm">Priority<select wire:model="priority" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">@foreach (['Low', 'Medium', 'High', 'Urgent'] as $option)<option>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if ($kind === 'Facility Use')
                    <flux:input label="Event date" type="date" wire:model="eventOn" /><flux:input label="Start" type="time" wire:model="startsAt" /><flux:input label="End" type="time" wire:model="endsAt" /><flux:input label="Guests" type="number" wire:model="guests" />
                @endif
                @if ($kind !== 'Facility Use')<flux:textarea :label="$kind === 'Correspondence' ? 'Notes' : 'Description'" wire:model="description" />@endif
                <flux:button type="submit" variant="primary">Save {{ $kind }}</flux:button>
            </form></flux:card>
        @endif
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-3">Record</th><th class="p-3">Property</th><th class="p-3">Details</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody>
            @forelse ($this->records as $record)
                <tr wire:key="office-{{ $kind }}-{{ $record->id }}" class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3">{{ $record->code }}</td><td class="p-3">{{ $record->property->code }}</td><td class="p-3">{{ $record->description ?? $record->purpose ?? $record->subject ?? $record->transactionType?->name ?? '' }}</td><td class="p-3"><x-status-pill :label="$record->status ?? $record->approval_status" /></td><td class="p-3">
                    @if ($kind === 'Facility Use')
                        @if ($record->approval_status === 'Pending' && $this->canManage())<flux:button size="sm" wire:click="act({{ $record->id }})">Approve</flux:button><flux:button size="sm" wire:click="decline({{ $record->id }})">Decline</flux:button>@endif
                        @if ($record->approval_status === 'Approved' && $record->refunded_on === null && (auth()->user()->hasRole(RoleName::Treasurer) || auth()->user()->hasRole(RoleName::Administrator)))<flux:button size="sm" wire:click="refund({{ $record->id }})">Refund deposit</flux:button>@endif
                    @elseif ($this->canManage())<flux:button size="sm" wire:click="act({{ $record->id }})">{{ match ($kind) { 'Transactions' => 'Complete', 'Requests & Complaints' => 'Resolve', 'Violations' => 'Issue notice', default => 'Acknowledge' } }}</flux:button>@endif
                </td></tr>
            @empty <tr><td colspan="5" class="p-6 text-center">No records yet.</td></tr> @endforelse
        </tbody></table></div>
        {{ $this->records->links() }}
        @if ($kind === 'Requests & Complaints' && $this->canManage())<flux:input label="Action taken before resolving" wire:model="actionNote" />@endif
        @if ($kind === 'Facility Use' && (auth()->user()->hasRole(RoleName::Treasurer) || auth()->user()->hasRole(RoleName::Administrator)))<flux:input label="Refund voucher/reference" wire:model="refundReference" />@endif
    </div>
</div>
