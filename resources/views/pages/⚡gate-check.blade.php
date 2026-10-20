<?php

use App\Enums\Permission;
use App\Services\GateCheckService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gate Check')] class extends Component
{
    public string $query = '';

    /** @var list<array{kind: string, verdict: string, name: string, details: string, property: string}> */
    public array $results = [];

    public bool $searched = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::GateView), 403);
    }

    public function check(GateCheckService $service): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::GateView), 403);
        $this->validate(['query' => ['required', 'string', 'min:2', 'max:100']]);
        $this->results = $service->check($this->query);
        $this->searched = true;
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <x-page-header title="Gate Check" subtitle="Check a plate, sticker, staff pass, or household name." />
    <flux:card><form wire:submit="check" class="flex flex-col gap-3 sm:flex-row sm:items-end"><flux:input label="Plate, sticker, pass, or name" wire:model="query" class="flex-1" /><flux:button type="submit" variant="primary">Check</flux:button></form></flux:card>
    @if ($searched)
        @forelse ($results as $index => $result)
            <flux:card wire:key="gate-result-{{ $index }}" class="flex flex-col gap-2"><div class="flex flex-wrap items-center justify-between gap-2"><flux:heading size="lg">{{ $result['name'] }}</flux:heading><flux:badge :color="in_array($result['verdict'], ['VALID', 'PASS OK', 'RESIDENT']) ? 'green' : 'red'">{{ $result['verdict'] }}</flux:badge></div><flux:text>{{ $result['kind'] }} · {{ $result['details'] }}</flux:text><flux:text>{{ $result['property'] }}</flux:text></flux:card>
        @empty
            <flux:card><flux:heading size="lg">NOT FOUND</flux:heading><flux:text>Treat as a visitor and follow gate procedure.</flux:text></flux:card>
        @endforelse
    @endif
</div>
