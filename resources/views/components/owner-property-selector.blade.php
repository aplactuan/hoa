@props(['properties', 'selected'])

@if ($properties->count() > 1)
    <flux:field class="w-full sm:max-w-xs">
        <flux:label>{{ __('Property') }}</flux:label>
        <flux:select wire:change="selectProperty($event.target.value)" :value="$selected">
            @foreach ($properties as $property)
                <flux:select.option :value="$property->id" wire:key="owned-property-{{ $property->id }}">
                    {{ $property->code }} · Block {{ $property->block }} Lot {{ $property->lot }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>
@endif
