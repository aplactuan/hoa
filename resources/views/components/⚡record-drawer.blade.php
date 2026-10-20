<?php

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $open = false;

    public string $title = '';

    public ?string $auditableType = null;

    public ?int $auditableId = null;

    #[On('open-record')]
    public function openRecord(string $title, string $type, int $id): void
    {
        $this->authorize('viewAny', AuditLog::class);

        $this->title = $title;
        $this->auditableType = $type;
        $this->auditableId = $id;
        $this->open = true;
        unset($this->entries);
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * @return Collection<int, array{id: int, action: string, summary: string|null, old: array<string, mixed>, new: array<string, mixed>, user: string, device: string, ip: string, at: string}>
     */
    #[Computed]
    public function entries(): Collection
    {
        if ($this->auditableType === null || $this->auditableId === null) {
            return collect();
        }

        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', $this->auditableType)
            ->where('auditable_id', $this->auditableId)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (AuditLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action->label(),
                    'summary' => $log->summary,
                    'old' => $log->present($log->old_values),
                    'new' => $log->present($log->new_values),
                    'user' => $log->user?->name ?? __('System'),
                    'device' => $log->device_label ?? __('Unassigned device'),
                    'ip' => $log->ip_address ?? '—',
                    'at' => $log->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '',
                ];
            });
    }
};
?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-40 bg-zinc-950/40" wire:click="close"></div>
        <aside class="fixed inset-y-0 right-0 z-50 flex w-full flex-col border-l border-zinc-200 bg-white shadow-xl sm:max-w-md dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-4 py-4 dark:border-zinc-700">
                <div class="min-w-0">
                    <flux:heading size="lg" class="truncate">{{ $title }}</flux:heading>
                    <flux:text>{{ __('Change history') }}</flux:text>
                </div>
                <flux:button type="button" variant="ghost" icon="x-mark" wire:click="close" />
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                @forelse ($this->entries as $entry)
                    <article wire:key="history-{{ $entry['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-status-pill :label="$entry['action']" color="sky" />
                            <flux:text>{{ $entry['at'] }}</flux:text>
                        </div>
                        @if (filled($entry['summary']))
                            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-200">{{ $entry['summary'] }}</p>
                        @endif
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $entry['user'] }} · {{ $entry['device'] }} · {{ $entry['ip'] }}
                        </p>
                        @if ($entry['old'] !== [] || $entry['new'] !== [])
                            <dl class="mt-3 space-y-2 text-sm">
                                @foreach (array_unique([...array_keys($entry['old']), ...array_keys($entry['new'])]) as $field)
                                    <div wire:key="history-{{ $entry['id'] }}-{{ $field }}">
                                        <dt class="font-medium text-zinc-800 dark:text-zinc-100">{{ str_replace('_', ' ', $field) }}</dt>
                                        <dd class="text-zinc-600 dark:text-zinc-300">
                                            {{ is_scalar($entry['old'][$field] ?? null) ? $entry['old'][$field] : '—' }}
                                            →
                                            {{ is_scalar($entry['new'][$field] ?? null) ? $entry['new'][$field] : '—' }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </article>
                @empty
                    <flux:text>{{ __('No changes have been recorded for this record.') }}</flux:text>
                @endforelse
            </div>
        </aside>
    @endif
</div>
