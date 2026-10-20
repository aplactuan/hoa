<?php

use App\Models\DuesStatement;
use App\Models\StatementAdjustment;
use App\Services\AccountService;
use App\Services\BillingService;
use App\Support\BillingMath;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Monthly dues')] class extends Component
{
    use WithPagination;

    public int $year;
    public int $month;
    public ?int $selectedStatementId = null;
    public string $adjustmentAmount = '';
    public string $adjustmentReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DuesStatement::class);
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function statements()
    {
        return DuesStatement::query()->with('property')->latest('due_on')->paginate(20);
    }

    #[Computed]
    public function pendingAdjustments()
    {
        return StatementAdjustment::query()->with('statement')->where('status', 'Pending')->latest()->limit(20)->get();
    }

    public function generate(BillingService $billing): void
    {
        $this->authorize('create', DuesStatement::class);
        $this->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12']]);
        $count = $billing->generatePeriod($this->year, $this->month, auth()->id());
        unset($this->statements);
        Flux::toast(variant: 'success', text: __(':count statements generated.', ['count' => $count]));
    }

    public function requestAdjustment(BillingService $billing): void
    {
        $statement = DuesStatement::query()->findOrFail($this->selectedStatementId);
        $this->authorize('requestAdjustment', $statement);
        $this->validate([
            'adjustmentAmount' => ['required', 'numeric', 'gt:0'],
            'adjustmentReason' => ['required', 'string', 'max:2000'],
        ]);
        $billing->requestAdjustment($statement, $this->adjustmentAmount, $this->adjustmentReason, auth()->id());
        $this->reset(['selectedStatementId', 'adjustmentAmount', 'adjustmentReason']);
        unset($this->pendingAdjustments);
        Flux::toast(variant: 'success', text: __('Adjustment requested.'));
    }

    public function approveAdjustment(int $id, BillingService $billing): void
    {
        $adjustment = StatementAdjustment::query()->with('statement')->findOrFail($id);
        $this->authorize('approveAdjustment', $adjustment->statement);
        $billing->approveAdjustment($adjustment, auth()->id());
        unset($this->pendingAdjustments);
        Flux::toast(variant: 'success', text: __('Adjustment approved.'));
    }
};
?>

<div>
    <div class="flex flex-col gap-6">
        <x-page-header title="Monthly dues" subtitle="Generate and review statements." />
        @can('create', App\Models\DuesStatement::class)
            <flux:card><form wire:submit="generate" class="flex flex-wrap items-end gap-3"><flux:input label="Year" type="number" wire:model="year" class="w-28" /><flux:input label="Month" type="number" min="1" max="12" wire:model="month" class="w-24" /><flux:button type="submit" variant="primary">Generate statements</flux:button></form></flux:card>
        @endcan
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-3">Statement</th><th class="p-3">Property</th><th class="p-3">Due</th><th class="p-3">Regular</th><th class="p-3">Previous</th><th class="p-3">Penalty</th><th class="p-3">Open balance</th></tr></thead><tbody>
            @forelse ($this->statements as $statement)
                <tr wire:key="statement-{{ $statement->id }}" class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3"><a href="{{ route('statements.print', $statement) }}" target="_blank" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $statement->code }}</a></td><td class="p-3"><a href="{{ route('properties.show', $statement->property) }}" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $statement->property->code }}</a></td><td class="p-3">{{ $statement->due_on->toDateString() }}</td><td class="p-3">₱{{ $statement->regular }}</td><td class="p-3">₱{{ $statement->previous_balance }}</td><td class="p-3">₱{{ $statement->penalty }}</td><td class="p-3">₱{{ BillingMath::decimal(app(AccountService::class)->statementBalanceCents($statement)) }}</td></tr>
            @empty <tr><td colspan="7" class="p-6 text-center">No statements yet.</td></tr> @endforelse
        </tbody></table></div>
        {{ $this->statements->links() }}
        @can('create', App\Models\DuesStatement::class)
            <flux:card><form wire:submit="requestAdjustment" class="grid gap-3 sm:grid-cols-4"><label class="text-sm">Statement<select wire:model="selectedStatementId" class="block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Select</option>@foreach ($this->statements as $statement)<option value="{{ $statement->id }}">{{ $statement->code }}</option>@endforeach</select></label><flux:input label="Credit amount" type="number" step="0.01" wire:model="adjustmentAmount" /><flux:input label="Reason" wire:model="adjustmentReason" /><flux:button type="submit" variant="primary">Request adjustment</flux:button></form></flux:card>
        @endcan
        @if (auth()->user()->hasRole(App\Enums\RoleName::Administrator))
            <flux:card><flux:heading size="lg">Pending adjustments</flux:heading>@foreach ($this->pendingAdjustments as $adjustment)<div wire:key="adjustment-{{ $adjustment->id }}" class="flex items-center justify-between border-t border-zinc-200 py-3 dark:border-zinc-700"><span>{{ $adjustment->statement->code }} · ₱{{ $adjustment->amount }} · {{ $adjustment->reason }}</span><flux:button size="sm" wire:click="approveAdjustment({{ $adjustment->id }})">Approve</flux:button></div>@endforeach</flux:card>
        @endif
    </div>
</div>
