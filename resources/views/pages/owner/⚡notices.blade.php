<?php

use App\Livewire\OwnerPortalComponent;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('My Notices')] class extends OwnerPortalComponent
{
    public function mount(): void
    {
        $this->initializeOwnedProperty();
    }

    #[Computed]
    public function violations()
    {
        return $this->selectedProperty
            ? $this->selectedProperty->violations()->with('violationType')->latest()->get()
            : collect();
    }

    #[Computed]
    public function correspondence()
    {
        return $this->selectedProperty
            ? $this->selectedProperty->correspondence()->latest()->get()
            : collect();
    }

    public function acknowledge(int $id): void
    {
        abort_unless($this->selectedProperty, 404);
        $letter = $this->selectedProperty->correspondence()->findOrFail($id);
        $letter->update([
            'status' => 'Acknowledged',
            'acknowledged_on' => now()->toDateString(),
            'updated_by' => $this->ownerUser()->id,
        ]);
        unset($this->correspondence);
        Flux::toast(variant: 'success', text: __('Notice acknowledged.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('My Notices')" :subtitle="__('Review HOA correspondence and any notices linked to your property.')" />
    @if (! $this->selectedProperty)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}" />
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />
        <div class="grid gap-6 xl:grid-cols-2">
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Correspondence') }}</flux:heading>@forelse ($this->correspondence as $letter)<div wire:key="owner-letter-{{ $letter->id }}" class="space-y-2 border-t border-zinc-200 py-3 dark:border-zinc-700"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $letter->code }} · {{ $letter->subject }}</strong><x-status-pill :label="$letter->status" /></div><flux:text>{{ $letter->type }} · {{ $letter->sender }}</flux:text>@if ($letter->notes)<flux:text>{{ $letter->notes }}</flux:text>@endif @if ($letter->status !== 'Acknowledged')<flux:button size="sm" wire:click="acknowledge({{ $letter->id }})">{{ __('Acknowledge') }}</flux:button>@endif</div>@empty<flux:text>{{ __('No correspondence.') }}</flux:text>@endforelse</flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('Violations') }}</flux:heading>@forelse ($this->violations as $violation)<div wire:key="owner-violation-{{ $violation->id }}" class="space-y-2 border-t border-zinc-200 py-3 dark:border-zinc-700"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $violation->code }} · {{ $violation->violationType->name }}</strong><x-status-pill :label="$violation->status" /></div><flux:text>{{ $violation->description }}</flux:text><flux:text>{{ __('Fine: ₱:fine', ['fine' => $violation->fine]) }}</flux:text></div>@empty<flux:text>{{ __('No violations.') }}</flux:text>@endforelse</flux:card>
        </div>
    @endif
</div>