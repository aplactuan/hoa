<?php

use App\Livewire\OwnerPortalComponent;
use App\Models\Facility;
use App\Services\OfficeService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Facility Bookings')] class extends OwnerPortalComponent
{
    public string $facilityId = '';
    public string $eventOn = '';
    public string $startsAt = '08:00';
    public string $endsAt = '09:00';
    public string $purpose = '';
    public int $guests = 0;

    public function mount(): void
    {
        $this->initializeOwnedProperty();
        $this->eventOn = now()->addDay()->toDateString();
    }

    #[Computed]
    public function facilities()
    {
        return Facility::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function reservations()
    {
        return $this->selectedProperty
            ? $this->selectedProperty->reservations()->with('facility')->latest()->get()
            : collect();
    }

    public function book(OfficeService $office): void
    {
        abort_unless($this->selectedProperty, 404);
        $data = $this->validate([
            'facilityId' => ['required', 'integer', Rule::exists('facilities', 'id')->where('is_active', true)],
            'eventOn' => ['required', 'date', 'after_or_equal:today'],
            'startsAt' => ['required', 'date_format:H:i'],
            'endsAt' => ['required', 'date_format:H:i', 'after:startsAt'],
            'purpose' => ['required', 'string', 'max:255'],
            'guests' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $office->bookFacility(
            $this->selectedProperty,
            Facility::query()->findOrFail((int) $data['facilityId']),
            $data['eventOn'],
            $data['startsAt'],
            $data['endsAt'],
            $data['purpose'],
            $data['guests'],
            $this->ownerUser()->id,
        );
        $this->reset(['facilityId', 'purpose', 'guests']);
        unset($this->reservations);
        Flux::toast(variant: 'success', text: __('Booking submitted for approval.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('Facility Bookings')" :subtitle="__('Request a facility schedule and follow its approval and payment status.')" />
    @if (! $this->selectedProperty)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current property access') }}" />
    @else
        <x-owner-property-selector :properties="$this->ownedProperties" :selected="$selectedPropertyId" />
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            <flux:card><form wire:submit="book" class="space-y-3"><flux:heading size="lg">{{ __('New booking') }}</flux:heading><flux:select label="{{ __('Facility') }}" wire:model="facilityId" placeholder="{{ __('Choose a facility') }}">@foreach ($this->facilities as $facility)<flux:select.option :value="$facility->id" wire:key="owner-facility-{{ $facility->id }}">{{ $facility->name }} · Fee ₱{{ $facility->fee }} · Deposit ₱{{ $facility->deposit }}</flux:select.option>@endforeach</flux:select><flux:input label="{{ __('Event date') }}" type="date" wire:model="eventOn" /><div class="grid grid-cols-2 gap-3"><flux:input label="{{ __('Starts') }}" type="time" wire:model="startsAt" /><flux:input label="{{ __('Ends') }}" type="time" wire:model="endsAt" /></div><flux:input label="{{ __('Purpose') }}" wire:model="purpose" /><flux:input label="{{ __('Guests') }}" type="number" min="0" wire:model="guests" /><flux:button type="submit" variant="primary">{{ __('Submit booking') }}</flux:button></form></flux:card>
            <flux:card class="space-y-3"><flux:heading size="lg">{{ __('My bookings') }}</flux:heading>@forelse ($this->reservations as $reservation)<div wire:key="owner-reservation-{{ $reservation->id }}" class="grid gap-2 border-t border-zinc-200 py-3 sm:grid-cols-[1fr_auto] dark:border-zinc-700"><div><strong>{{ $reservation->code }} · {{ $reservation->facility->name }}</strong><flux:text>{{ $reservation->event_on->toDateString() }} · {{ $reservation->starts_at }}–{{ $reservation->ends_at }} · {{ $reservation->purpose }}</flux:text><flux:text>Fee ₱{{ $reservation->fee }} · Deposit ₱{{ $reservation->deposit }} · {{ $reservation->payment_status }}</flux:text></div><x-status-pill :label="$reservation->approval_status" /></div>@empty<flux:text>{{ __('No bookings yet.') }}</flux:text>@endforelse</flux:card>
        </div>
    @endif
</div>