<?php

use App\Enums\AuditAction;
use App\Livewire\DataTableComponent;
use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Audit Log')] class extends DataTableComponent
{
    public function mount(): void
    {
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array{id: int, action: string, record: string, user: string, device: string, ip: string, at: string, type: string|null, record_id: int|null, title: string}>
     */
    #[Computed]
    public function records()
    {
        $query = AuditLog::query()->with('user');

        if ($this->status !== 'all') {
            $query->where('action', $this->status);
        }

        if ($this->search !== '') {
            $like = '%'.$this->search.'%';
            $query->where(function ($query) use ($like): void {
                $query->where('summary', 'like', $like)
                    ->orWhere('device_label', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', $like));
            });
        }

        $column = in_array($this->sortBy, $this->sortableColumns(), true) ? $this->sortBy : 'created_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($column, $direction)
            ->paginate(10)
            ->through(function (AuditLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action->label(),
                    'record' => class_basename((string) $log->auditable_type).($log->auditable_id ? ' #'.$log->auditable_id : ''),
                    'user' => $log->user?->name ?? __('System'),
                    'device' => $log->device_label ?? __('Unassigned device'),
                    'ip' => $log->ip_address ?? '—',
                    'at' => $log->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '',
                    'type' => $log->auditable_type,
                    'record_id' => $log->auditable_id,
                    'title' => class_basename((string) $log->auditable_type).' #'.$log->auditable_id,
                ];
            });
    }

    /**
     * @return array<string, string>
     */
    protected function statusChips(): array
    {
        $chips = ['all' => 'All'];

        foreach (AuditAction::cases() as $action) {
            $chips[$action->value] = $action->label();
        }

        return $chips;
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'action'];
    }

    public function openHistory(int $id): void
    {
        $log = AuditLog::query()->findOrFail($id);
        $this->authorize('view', $log);

        if ($log->auditable_type === null || $log->auditable_id === null) {
            return;
        }

        $this->dispatch(
            'open-record',
            title: class_basename($log->auditable_type).' #'.$log->auditable_id,
            type: $log->auditable_type,
            id: $log->auditable_id,
        );
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header
        :title="__('Audit Log')"
        :subtitle="__('Creates, updates, deletes, payments, voids, sign-ins, and exports. Entries cannot be changed.')"
    />

    <x-data-table :records="$this->records" :status="$status" :chips="$this->statusChips()">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('When') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'action'" :direction="$sortDirection" wire:click="sort('action')">{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Record') }}</flux:table.column>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Device') }}</flux:table.column>
                <flux:table.column>{{ __('IP') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->records as $entry)
                    <flux:table.row wire:key="audit-{{ $entry['id'] }}">
                        <flux:table.cell>{{ $entry['at'] }}</flux:table.cell>
                        <flux:table.cell><x-status-pill :label="$entry['action']" color="sky" /></flux:table.cell>
                        <flux:table.cell>
                            @if ($entry['type'])
                                <flux:button size="sm" variant="ghost" wire:click="openHistory({{ $entry['id'] }})">
                                    {{ $entry['record'] }}
                                </flux:button>
                            @else
                                {{ $entry['record'] }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $entry['user'] }}</flux:table.cell>
                        <flux:table.cell>{{ $entry['device'] }}</flux:table.cell>
                        <flux:table.cell>{{ $entry['ip'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-data-table>
</div>
