<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component
{
    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->paginate(20);
    }

    public function markRead(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        unset($this->notifications);
    }
};
?>

<div class="space-y-6">
    <x-page-header title="Notifications" subtitle="Approval requests and urgent office work." />
    <div class="space-y-3">
        @forelse ($this->notifications as $notification)
            <flux:card wire:key="notification-{{ $notification->id }}" class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading>{{ $notification->data['title'] }}</flux:heading>
                    <flux:text>{{ $notification->data['message'] }}</flux:text>
                    <flux:text class="text-xs">{{ $notification->created_at->diffForHumans() }}</flux:text>
                </div>
                <div class="flex gap-2">
                    @if (Route::has($notification->data['route'] ?? ''))
                        <flux:button size="sm" :href="route($notification->data['route'])">Open</flux:button>
                    @endif
                    @if ($notification->read_at === null)
                        <flux:button size="sm" wire:click="markRead('{{ $notification->id }}')">Mark read</flux:button>
                    @endif
                </div>
            </flux:card>
        @empty
            <flux:card><flux:text>No notifications yet.</flux:text></flux:card>
        @endforelse
    </div>
    {{ $this->notifications->links() }}
</div>
