<?php

use App\Livewire\OwnerPortalComponent;
use App\Services\StatementOfAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My Account')] class extends OwnerPortalComponent
{
    public string $asOf = '';

    public string $soaTab = 'schedule';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    #[Computed]
    public function accountProperties(): Collection
    {
        return $this->ownerUser()->currentOwnedProperties()
            ->with(['statements.billingPeriod', 'transactions.transactionType'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function statement(): array
    {
        return app(StatementOfAccount::class)->forProperties($this->accountProperties, $this->asOfDate());
    }

    public function updatedAsOf(): void
    {
        unset($this->statement);
    }

    private function asOfDate(): Carbon
    {
        $date = Carbon::make($this->asOf ?: null) ?? now();

        return $date->isFuture() ? now() : $date;
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Account')" :subtitle="__('Your statement of account: membership fee, monthly dues, penalties, discounts and payments for all your properties.')" />

    @if ($this->accountProperties->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}">{{ __('Contact the HOA office to review your account link.') }}</flux:callout>
    @else
        <flux:card class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ $this->statement['owner_name'] }}</flux:heading>
                @foreach ($this->statement['properties'] as $property)
                    <flux:text wire:key="soa-property-{{ $property['code'] }}">{{ $property['code'] }} · {{ $property['block_lot'] }}@if ($property['address']) · {{ $property['address'] }}@endif</flux:text>
                @endforeach
                <flux:text size="sm">
                    @if ($this->statement['member_since'])
                        {{ __('Member since :date', ['date' => Carbon::parse($this->statement['member_since'])->format('M j, Y')]) }} ·
                    @endif
                    {{ __('Statement as of :date', ['date' => Carbon::parse($this->statement['as_of'])->format('M j, Y')]) }}
                </flux:text>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <flux:input type="date" :label="__('Statement as of')" wire:model.live="asOf" :max="now()->toDateString()" />
                <flux:button icon="arrow-down-tray" :href="route('owner.statement-of-account.csv', ['as_of' => $this->statement['as_of']])">{{ __('Excel / Sheets') }}</flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" :href="route('owner.statement-of-account.print', ['as_of' => $this->statement['as_of']])" target="_blank">{{ __('Export PDF') }}</flux:button>
            </div>
        </flux:card>

        <x-statement-of-account :account="$this->statement" :tab="$soaTab" />

        <div class="grid gap-4 xl:grid-cols-2">
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Monthly statements') }}</flux:heading>
                @forelse ($this->accountProperties->flatMap->statements->sortByDesc('due_on') as $statement)
                    <div wire:key="owner-statement-{{ $statement->id }}" class="flex items-center justify-between gap-3 border-t border-zinc-200 py-2 dark:border-zinc-700">
                        <span>{{ $statement->code }} · {{ $statement->billingPeriod->year }}-{{ str_pad($statement->billingPeriod->month, 2, '0', STR_PAD_LEFT) }}</span>
                        <flux:button size="sm" :href="route('statements.print', $statement)" target="_blank">{{ __('PDF') }}</flux:button>
                    </div>
                @empty
                    <flux:text>{{ __('No statements yet.') }}</flux:text>
                @endforelse
            </flux:card>
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Service transactions') }}</flux:heading>
                @forelse ($this->accountProperties->flatMap->transactions->sortByDesc('id') as $transaction)
                    <div wire:key="owner-transaction-{{ $transaction->id }}" class="border-t border-zinc-200 py-2 dark:border-zinc-700">{{ $transaction->code }} · {{ $transaction->transactionType?->name }} · {{ $transaction->status }} · ₱{{ $transaction->amount }}</div>
                @empty
                    <flux:text>{{ __('No service transactions.') }}</flux:text>
                @endforelse
            </flux:card>
        </div>
        <flux:callout icon="information-circle">{{ __('Online payment is not available. Pay through an approved HOA channel, then check this page for the posted receipt. Payments are credited as of the date you paid.') }}</flux:callout>
    @endif
</div>
