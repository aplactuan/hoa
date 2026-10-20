@props([
    'title',
    'submit' => 'save',
])

<flux:modal wire:model.self="showModal" class="w-full md:max-w-lg">
    <form wire:submit="{{ $submit }}" class="space-y-6">
        <flux:heading size="lg">{{ $title }}</flux:heading>

        <div class="space-y-4">
            {{ $slot }}
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
