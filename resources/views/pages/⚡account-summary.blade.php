<?php

use App\Enums\Permission;
use App\Models\Owner;
use App\Services\AccountService;
use App\Support\BillingMath;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Owner Account Summary')] class extends Component
{
    public string $ownerCode = '';
    public bool $searched = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::AccountSummaryView), 403);
    }

    public function lookup(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::AccountSummaryView), 403);
        $this->validate(['ownerCode' => ['required', 'string', 'max:30']]);
        $this->ownerCode = strtoupper(trim($this->ownerCode));
        $this->searched = true;
        unset($this->owner, $this->summary);
    }

    #[Computed]
    public function owner(): ?Owner
    {
        if (! $this->searched) {
            return null;
        }

        return Owner::query()->with([
            'property.street', 'property.section', 'property.statements.billingPeriod',
            'property.payments', 'property.charges', 'property.vehicles',
            'property.familyMembers', 'property.houseStaff', 'property.cases',
            'property.violations', 'property.correspondence', 'property.transactions',
        ])->where('code', $this->ownerCode)->first();
    }

    #[Computed]
    public function summary(): array
    {
        return $this->owner && $this->owner->status === 'Current'
            ? app(AccountService::class)->summary($this->owner->property)
            : [];
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header title="Owner Account Summary" subtitle="Look up an owner ID to review the linked account." />
        <flux:card class="print:hidden"><form wire:submit="lookup" class="flex flex-wrap items-end gap-3"><flux:input label="Owner ID" wire:model="ownerCode" placeholder="OWN-0001" /><flux:button type="submit" variant="primary">Look up</flux:button></form></flux:card>
        @if ($searched && ! $this->owner)
            <flux:card><flux:text>No owner with this ID was found.</flux:text></flux:card>
        @elseif ($this->owner)
            <div class="flex justify-end print:hidden"><flux:button onclick="window.print()">Print account summary</flux:button></div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:card><flux:heading size="lg">{{ $this->owner->name }}</flux:heading><flux:text>{{ $this->owner->code }} · {{ $this->owner->status }}</flux:text><flux:text>{{ $this->owner->phone }} · {{ $this->owner->email }}</flux:text></flux:card>
                <flux:card><flux:heading size="lg">Property</flux:heading><flux:text>{{ $this->owner->property->code }} · Block {{ $this->owner->property->block }} Lot {{ $this->owner->property->lot }}</flux:text><flux:text>{{ $this->owner->property->street?->name }}</flux:text></flux:card>
                <flux:card><flux:heading size="lg">Account</flux:heading>
                    @if ($this->owner->status === 'Current')
                        <flux:text>{{ $this->summary['standing'] }}</flux:text><flux:text>Outstanding: ₱{{ BillingMath::decimal($this->summary['outstanding_cents']) }}</flux:text><flux:text>Advance credit: ₱{{ BillingMath::decimal($this->summary['credit_cents']) }}</flux:text>
                    @else
                        <flux:text>Former ownership: {{ $this->owner->started_on->toDateString() }} to {{ $this->owner->ended_on?->toDateString() }}. Current account balance belongs to the present owner.</flux:text>
                    @endif
                </flux:card>
            </div>
            @if ($this->owner->status === 'Current')
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:card><flux:heading size="lg">Monthly dues</flux:heading>@foreach ($this->owner->property->statements as $statement)<div wire:key="summary-statement-{{ $statement->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $statement->code }} · {{ $statement->billingPeriod->year }}-{{ str_pad($statement->billingPeriod->month, 2, '0', STR_PAD_LEFT) }} · ₱{{ $statement->regular }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Payments</flux:heading>@foreach ($this->owner->property->payments as $payment)<div wire:key="summary-payment-{{ $payment->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $payment->or_no }} · {{ $payment->paid_on->toDateString() }} · ₱{{ $payment->amount }} · {{ $payment->status }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Other charges</flux:heading>@foreach ($this->owner->property->charges as $charge)<div wire:key="summary-charge-{{ $charge->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $charge->description }} · ₱{{ $charge->amount }} · {{ $charge->status }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Transactions</flux:heading>@foreach ($this->owner->property->transactions as $transaction)<div wire:key="summary-transaction-{{ $transaction->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $transaction->code }} · {{ $transaction->status }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Vehicles</flux:heading>@foreach ($this->owner->property->vehicles as $vehicle)<div wire:key="summary-vehicle-{{ $vehicle->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $vehicle->plate_no }} · {{ $vehicle->status }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Family and staff</flux:heading>@foreach ($this->owner->property->familyMembers as $member)<div wire:key="summary-family-{{ $member->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $member->name }} · {{ $member->relationship }}</div>@endforeach @foreach ($this->owner->property->houseStaff as $staff)<div wire:key="summary-staff-{{ $staff->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $staff->name }} · {{ $staff->role }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Requests and violations</flux:heading>@foreach ($this->owner->property->cases as $case)<div wire:key="summary-case-{{ $case->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $case->code }} · {{ $case->status }}</div>@endforeach @foreach ($this->owner->property->violations as $violation)<div wire:key="summary-violation-{{ $violation->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $violation->code }} · {{ $violation->status }}</div>@endforeach</flux:card>
                    <flux:card><flux:heading size="lg">Correspondence</flux:heading>@foreach ($this->owner->property->correspondence as $letter)<div wire:key="summary-letter-{{ $letter->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $letter->subject }} · {{ $letter->status }}</div>@endforeach</flux:card>
                </div>
            @endif
        @endif
    </div>
</div>
