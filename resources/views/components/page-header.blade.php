@props([
    'title',
    'subtitle' => null,
])

<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <flux:heading size="xl" class="truncate">{{ $title }}</flux:heading>
        @if (filled($subtitle))
            <flux:text class="mt-1">{{ $subtitle }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
