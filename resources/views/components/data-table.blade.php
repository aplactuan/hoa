@props([
    'records',
    'status' => 'all',
    'chips' => [
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
])

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Filter records') }}"
            class="w-full lg:max-w-xs"
        />

        <div class="flex flex-wrap gap-2">
            @foreach ($chips as $value => $label)
                <flux:button
                    size="sm"
                    :variant="$status === $value ? 'primary' : 'ghost'"
                    wire:click="filterStatus('{{ $value }}')"
                    wire:key="chip-{{ $value }}"
                >
                    {{ $label }}
                </flux:button>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    @isset($totals)
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
            {{ $totals }}
        </div>
    @endisset

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:text>
            @if ($records->total() === 0)
                {{ __('No records') }}
            @else
                {{ __('Showing :from–:to of :total', [
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                    'total' => $records->total(),
                ]) }}
            @endif
        </flux:text>

        <div>
            {{ $records->links() }}
        </div>
    </div>
</div>
