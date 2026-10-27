<?php

use App\Livewire\OwnerPortalComponent;
use App\Services\AccountService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My Dashboard')] class extends OwnerPortalComponent
{
    public function mount(): void
    {
        $this->initializeOwnedProperty();
    }

    #[Computed]
    public function account(): array
    {
        return $this->selectedProperty
            ? app(AccountService::class)->summary($this->selectedProperty)
            : [];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Dashboard')" :subtitle="__('Your Green Meadows property, account, and requests in one place.')" />

    @if (! $this->selectedProperty)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}">
            {{ __('Your account is not linked to a current ownership record. Contact the HOA office.') }}
        </flux:callout>
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card>
                <flux:text>{{ __('Property') }}</flux:text>
                <flux:heading size="lg">{{ $this->selectedProperty->code }}</flux:heading>
                <flux:text>{{ __('Block :block, Lot :lot', ['block' => $this->selectedProperty->block, 'lot' => $this->selectedProperty->lot]) }}</flux:text>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Account standing') }}</flux:text>
                <flux:heading size="lg">{{ $this->account['standing'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Outstanding') }}</flux:text>
                <flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->account['outstanding_cents']) }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Advance credit') }}</flux:text>
                <flux:heading size="lg">₱{{ App\Support\BillingMath::decimal($this->account['credit_cents']) }}</flux:heading>
            </flux:card>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('My Property') }}</flux:heading>
                <flux:text>{{ __('Review household members, vehicles, stickers, and private documents.') }}</flux:text>
                <flux:button :href="route('owner.property')" wire:navigate>{{ __('Open property') }}</flux:button>
            </flux:card>
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('My Account') }}</flux:heading>
                <flux:text>{{ __('Review statements, receipts, charges, and transaction history.') }}</flux:text>
                <flux:button :href="route('owner.account')" wire:navigate>{{ __('Open account') }}</flux:button>
            </flux:card>
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('Requests') }}</flux:heading>
                <flux:text>{{ __('File a concern or request an HOA service and follow its status.') }}</flux:text>
                <flux:button :href="route('owner.requests')" wire:navigate>{{ __('Open requests') }}</flux:button>
            </flux:card>
        </div>
    @endif
</div>