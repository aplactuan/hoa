<?php

use App\Models\Charge;
use App\Models\Property;
use App\Services\AccountService;
use App\Services\BillingService;
use App\Support\BillingMath;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Other charges')] class extends Component
{
    use WithPagination;

    public string $propertyId = '';
    public string $description = '';
    public string $amount = '';
    public string $dueOn = '';
    public ?int $selectedChargeId = null;
    public string $waiverReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Charge::class);
    }

    #[Computed]
    public function charges()
    {
        return Charge::query()->with('property')->latest()->paginate(20);
    }

    #[Computed]
    public function properties()
    {
        return Property::query()->orderBy('block')->orderBy('lot')->get(['id', 'code', 'block', 'lot']);
    }

    public function save(BillingService $billing): void
    {
        $this->authorize('create', Charge::class);
        $data = $this->validate([
            'propertyId' => ['required', 'integer', 'exists:properties,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'dueOn' => ['nullable', 'date'],
        ]);
        $property = Property::query()->findOrFail($data['propertyId']);
        $billing->createCharge($property, $data['description'], $data['amount'], $data['dueOn'] ?: null, auth()->id());
        $this->reset(['propertyId', 'description', 'amount', 'dueOn']);
        unset($this->charges);
        Flux::toast(variant: 'success', text: __('Charge created.'));
    }

    public function requestWaiver(BillingService $billing): void
    {
        $charge = Charge::query()->findOrFail($this->selectedChargeId);
        $this->authorize('requestWaiver', $charge);
        $this->validate(['waiverReason' => ['required', 'string', 'max:2000']]);
        $billing->requestWaiver($charge, $this->waiverReason, auth()->id());
        $this->reset(['selectedChargeId', 'waiverReason']);
        unset($this->charges);
        Flux::toast(variant: 'success', text: __('Waiver requested.'));
    }

    public function approveWaiver(int $id, BillingService $billing): void
    {
        $charge = Charge::query()->findOrFail($id);
        $this->authorize('approveWaiver', $charge);
        $billing->approveWaiver($charge, auth()->id());
        unset($this->charges);
        Flux::toast(variant: 'success', text: __('Waiver approved.'));
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header title="Other charges" subtitle="Manual fees and fines linked to properties." />
        @can('create', App\Models\Charge::class)
            <flux:card><form wire:submit="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><label class="text-sm">Property<select wire:model="propertyId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->properties as $property)<option value="{{ $property->id }}">{{ $property->code }} · Block {{ $property->block }} Lot {{ $property->lot }}</option>@endforeach</select></label><flux:input label="Description" wire:model="description" /><flux:input label="Amount" type="number" step="0.01" wire:model="amount" /><flux:input label="Due date" type="date" wire:model="dueOn" /><flux:button type="submit" variant="primary">Add charge</flux:button></form></flux:card>
        @endcan
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-3">Charge</th><th class="p-3">Property</th><th class="p-3">Description</th><th class="p-3">Amount</th><th class="p-3">Open</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody>
            @forelse ($this->charges as $charge)
                <tr wire:key="charge-{{ $charge->id }}" class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3">{{ $charge->code }}</td><td class="p-3">{{ $charge->property->code }}</td><td class="p-3">{{ $charge->description }}</td><td class="p-3">₱{{ $charge->amount }}</td><td class="p-3">₱{{ BillingMath::decimal(app(AccountService::class)->chargeBalanceCents($charge)) }}</td><td class="p-3"><x-status-pill :label="$charge->status" /></td><td class="p-3">@can('approveWaiver', $charge) @if ($charge->status === 'Waiver Requested')<flux:button size="sm" wire:click="approveWaiver({{ $charge->id }})">Approve waiver</flux:button>@endif @endcan</td></tr>
            @empty <tr><td colspan="7" class="p-6 text-center">No charges yet.</td></tr> @endforelse
        </tbody></table></div>
        {{ $this->charges->links() }}
        @can('create', App\Models\Charge::class)
            <flux:card><form wire:submit="requestWaiver" class="flex flex-wrap items-end gap-3"><label class="text-sm">Charge<select wire:model="selectedChargeId" class="block rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->charges as $charge) @if ($charge->status === 'Unpaid')<option value="{{ $charge->id }}">{{ $charge->code }}</option>@endif @endforeach</select></label><flux:input label="Reason" wire:model="waiverReason" /><flux:button type="submit">Request waiver</flux:button></form></flux:card>
        @endcan
    </div>
</div>
