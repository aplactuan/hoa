<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<form action="{{ route('search') }}" method="get" role="search" class="w-full">
    <flux:input
        name="q"
        value="{{ request('q') }}"
        placeholder="{{ __('Search records') }}"
        icon="magnifying-glass"
        autocomplete="off"
    />
</form>
