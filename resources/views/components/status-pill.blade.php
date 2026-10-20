@props([
    'active' => null,
    'label' => null,
    'color' => null,
])

@php
    $label ??= $active === true ? __('Active') : ($active === false ? __('Inactive') : '');
    $color ??= match ($active) {
        true => 'green',
        false => 'zinc',
        default => 'sky',
    };
@endphp

<flux:badge size="sm" :color="$color">{{ $label }}</flux:badge>
