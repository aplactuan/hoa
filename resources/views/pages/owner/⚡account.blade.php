<?php

use App\Livewire\OwnerPortalComponent;
use App\Services\AccountService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My Account')] class extends OwnerPortalComponent
{
    public function mount(): void
    {
        $this->initializeOwnedProperty();
    }

    #[Computed]
    public function accountRecord()
    {
        return $this->selectedProperty
            ? $this->ownerUser()->currentOwnedProperties()->with([
                'statements.billingPeriod', 'payments', 'charges', 'transactions.transactionType',
            ])->findOrFail($this->selectedProperty->id)
            : null;
    }

    #[Computed]
    public function summary(): array
    {
        return $this->accountRecord ? app(AccountService::class)->summary($this->accountRecord) : [];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Account')" :subtitle="__('Statements, receipts, charges, and account standing for your property.')" />
    @if (! $this->accountRecord)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}">{{ __('Contact the HOA office to review your account link.') }}</flux:callout>
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card><flux:text>{{ __('Standing') }}</flux:text><flux:heading size="lg">{{ $this->summary['standing'] }}</flux:heading></flux:card>
            <flux:card><flux:text>{{ __('Outstanding') }}</flux:text><flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->summary['outstanding_cents']) }}</flux:heading></flux:card>
            <flux:card><flux:text>{{ __('Advance credit') }}</flux:text><flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->summary['credit_cents']) }}</flux:heading></flux:card>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Statements') }}</flux:heading>@forelse ($this->accountRecord->statements->sortByDesc('id') as $statement)<div wire:key="owner-statement-{{ $statement->id }}" class="flex items-center justify-between gap-3 border-t border-zinc-200 py-2 dark:border-zinc-700"><span>{{ $statement->code }} · {{ $statement->billingPeriod->year }}-{{ str_pad($statement->billingPeriod->month, 2, '0', STR_PAD_LEFT) }} · ₱{{ $statement->total }}</span><flux:button size="sm" :href="route('statements.print', $statement)" target="_blank">{{ __('PDF') }}</flux:button></div>@empty<flux:text>{{ __('No statements yet.') }}</flux:text>@endforelse</flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Receipts') }}</flux:heading>@forelse ($this->accountRecord->payments->sortByDesc('paid_on') as $payment)<div wire:key="owner-payment-{{ $payment->id }}" class="flex items-center justify-between gap-3 border-t border-zinc-200 py-2 dark:border-zinc-700"><span>{{ $payment->or_no }} · {{ $payment->paid_on->toDateString() }} · ₱{{ $payment->amount }} · {{ $payment->status }}</span><flux:button size="sm" :href="route('receipts.print', $payment)" target="_blank">{{ __('PDF') }}</flux:button></div>@empty<flux:text>{{ __('No receipts yet.') }}</flux:text>@endforelse</flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Other charges') }}</flux:heading>@forelse ($this->accountRecord->charges->sortByDesc('id') as $charge)<div wire:key="owner-charge-{{ $charge->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $charge->description }} · ₱{{ $charge->amount }} · {{ $charge->status }}</div>@empty<flux:text>{{ __('No other charges.') }}</flux:text>@endforelse</flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Service transactions') }}</flux:heading>@forelse ($this->accountRecord->transactions->sortByDesc('id') as $transaction)<div wire:key="owner-transaction-{{ $transaction->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $transaction->code }} · {{ $transaction->transactionType?->name }} · {{ $transaction->status }} · ₱{{ $transaction->amount }}</div>@empty<flux:text>{{ __('No service transactions.') }}</flux:text>@endforelse</flux:card>
        </div>
        <flux:callout icon="information-circle">{{ __('Online payment is not available. Pay through an approved HOA channel, then check this page for the posted receipt.') }}</flux:callout>
    @endif
</div>