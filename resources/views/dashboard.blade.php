<x-layouts::app :title="__('Dashboard')">
    @php
        $overview = app(App\Services\DashboardService::class)->overview();
        $chartMaximum = max(1, ...array_column($overview['collections'], 'cents'));
    @endphp
    <div class="flex flex-col gap-6">
        <x-page-header title="Dashboard" subtitle="Green Meadows Phase 1 HOA office overview." />
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card><flux:text>Properties</flux:text><flux:heading size="xl">{{ $overview['properties'] }}</flux:heading><flux:text>{{ $overview['occupied'] }} occupied · {{ $overview['vacant'] }} vacant</flux:text></flux:card>
            <flux:card><flux:text>Total outstanding</flux:text><flux:heading size="xl">₱{{ App\Support\BillingMath::decimal($overview['outstanding_cents']) }}</flux:heading></flux:card>
            <flux:card><flux:text>Current month collection</flux:text><flux:heading size="xl">{{ $overview['collection_percent'] }}%</flux:heading></flux:card>
            <flux:card><flux:text>Good standing</flux:text><flux:heading size="xl">{{ $overview['good_standing'] }} / {{ $overview['properties'] }}</flux:heading><flux:text>{{ $overview['payments_recorded'] }} payments recorded</flux:text></flux:card>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card><flux:heading size="lg">Collections, last six months</flux:heading><div class="mt-4 flex h-40 items-end gap-3">
                @foreach ($overview['collections'] as $month)
                    <div class="flex flex-1 flex-col items-center gap-2"><div class="w-full rounded-t bg-emerald-600" style="height: {{ max(4, round($month['cents'] / $chartMaximum * 120)) }}px"></div><span class="text-xs">{{ $month['label'] }}</span></div>
                @endforeach
            </div></flux:card>
            <flux:card><flux:heading size="lg">Needs attention</flux:heading><div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                @foreach ($overview['attention'] as $label => $count)
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800"><strong>{{ $count }}</strong> {{ str_replace('_', ' ', $label) }}</div>
                @endforeach
            </div></flux:card>
            <flux:card><flux:heading size="lg">Accounts with arrears</flux:heading><div class="mt-3 flex flex-col gap-2">
                @foreach ($overview['top_arrears'] as $account)
                    @if ($account['summary']['outstanding_cents'] > 0)
                        <div class="flex justify-between gap-3 border-t border-zinc-200 py-2 text-sm dark:border-zinc-700"><span>Block {{ $account['property']->block }} Lot {{ $account['property']->lot }} · {{ $account['property']->currentOwner?->name ?? 'No owner' }}</span><strong>₱{{ App\Support\BillingMath::decimal($account['summary']['outstanding_cents']) }}</strong></div>
                    @endif
                @endforeach
            </div></flux:card>
            <flux:card><flux:heading size="lg">Open cases</flux:heading><div class="mt-3 flex flex-col gap-2">@foreach ($overview['open_cases'] as $case)<div class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $case->code }} · {{ $case->priority }} · {{ $case->description }}</div>@endforeach</div><flux:heading size="lg" class="mt-4">Upcoming reservations</flux:heading><div class="mt-3 flex flex-col gap-2">@foreach ($overview['upcoming_reservations'] as $reservation)<div class="border-t border-zinc-200 py-2 text-sm dark:border-zinc-700">{{ $reservation->facility->name }} · {{ $reservation->event_on->toDateString() }} · {{ $reservation->purpose }}</div>@endforeach</div></flux:card>
        </div>
    </div>
</x-layouts::app>
