@props([
    'account',
    'tab' => 'schedule',
])

@php
    use App\Services\StatementOfAccount as Soa;

    $balance = $account['balance_cents'];
    $balanceLabel = $balance < 0 ? __('Credit balance') : __('Balance due');
    $balanceNote = match (true) {
        $balance < 0 => __('Advance payment on file'),
        $balance === 0 => __('Fully paid'),
        $account['overdue_cents'] > 0 => Soa::peso($account['overdue_cents']).' '.__('overdue'),
        $account['next_due_on'] !== null => __('Due by :date', ['date' => \Illuminate\Support\Carbon::parse($account['next_due_on'])->format('M j, Y')]),
        default => '',
    };
    $penaltyNote = match (true) {
        $account['pending_penalties_cents'] > 0 => Soa::peso($account['pending_penalties_cents']).' '.__('more if unpaid when the amnesty ends'),
        $account['waived_penalties_cents'] > 0 => Soa::peso($account['waived_penalties_cents']).' '.__('waived under amnesty'),
        default => trans_choice(':count month-late charge|:count month-late charges', $account['penalty_months']),
    };
    $longDate = fn (?string $date): string => $date === null ? '—' : \Illuminate\Support\Carbon::parse($date)->format('M j, Y');
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap gap-2">
        @foreach ($account['rules'] as $rule)
            <flux:badge size="sm" color="zinc" wire:key="soa-rule-{{ $loop->index }}">{{ $rule }}</flux:badge>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-accent bg-accent p-6 text-accent-foreground">
            <div class="text-sm opacity-85">{{ $balanceLabel }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ Soa::peso(abs($balance)) }}</div>
            <div class="mt-1 text-sm opacity-85">{{ $balanceNote }}</div>
        </div>
        <flux:card>
            <flux:text>{{ __('Total billed') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ Soa::peso($account['billed_cents']) }}</flux:heading>
            <flux:text size="sm">{{ __('Fees') }} {{ Soa::amount($account['billed_charges_cents']) }} · {{ __('Dues') }} {{ Soa::amount($account['billed_dues_cents']) }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:text>{{ __('Penalties accrued') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ Soa::peso($account['billed_penalties_cents']) }}</flux:heading>
            <flux:text size="sm">{{ $penaltyNote }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:text>{{ __('Total paid') }}</flux:text>
            <flux:heading size="xl" class="tabular-nums">{{ Soa::peso($account['paid_cents']) }}</flux:heading>
            <flux:text size="sm">{{ trans_choice(':count payment|:count payments', count($account['payments'])) }}@if ($account['future_payments'] > 0) · {{ $account['future_payments'] }} {{ __('after the as-of date') }}@endif</flux:text>
        </flux:card>
        <flux:card>
            <flux:text>{{ __('Discount applied') }}</flux:text>
            <flux:heading size="xl" :class="\Illuminate\Support\Arr::toCssClasses(['tabular-nums', 'text-green-700 dark:text-green-400' => $account['discount_cents'] > 0])">{{ Soa::peso($account['discount_cents']) }}</flux:heading>
            <flux:text size="sm">{{ $account['discount_cents'] > 0 ? __('Early payment discount') : __('No early-payment discount') }}</flux:text>
        </flux:card>
    </div>

    @foreach ($account['promos'] as $promo)
        @if ($promo['eligible'] && ! $promo['qualified'] && $promo['promo']->is_active && $promo['promo']->pay_by->toDateString() >= $account['as_of'])
            <flux:callout wire:key="soa-promo-{{ $promo['promo']->id }}" icon="sparkles" color="emerald" heading="{{ $promo['promo']->name }}">
                {{ __('Pay :remaining more on or before :date (:required in total, covering everything billed through :month) to get :discount off.', [
                    'remaining' => Soa::peso($promo['remaining_cents']),
                    'date' => $promo['promo']->pay_by->format('M j, Y'),
                    'required' => Soa::peso($promo['required_cents']),
                    'month' => $promo['promo']->covers_to->format('M Y'),
                    'discount' => Soa::peso($promo['discount_cents']),
                ]) }}
            </flux:callout>
        @endif
    @endforeach

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" :variant="$tab === 'schedule' ? 'primary' : 'ghost'" wire:click="$set('soaTab', 'schedule')">{{ __('Monthly schedule') }}</flux:button>
            <flux:button size="sm" :variant="$tab === 'ledger' ? 'primary' : 'ghost'" wire:click="$set('soaTab', 'ledger')">{{ __('Transaction ledger') }}</flux:button>
            <flux:button size="sm" :variant="$tab === 'payments' ? 'primary' : 'ghost'" wire:click="$set('soaTab', 'payments')">{{ __('Payments on file') }}</flux:button>
        </div>

        @if ($tab === 'schedule')
            <flux:card class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Item') }}</flux:table.column>
                        <flux:table.column>{{ __('Deadline') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Penalty') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Paid') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
                        <flux:table.column>{{ __('Dues paid on') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($account['charges'] as $charge)
                            <flux:table.row wire:key="soa-charge-{{ $charge['code'] }}">
                                <flux:table.cell>{{ $charge['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $longDate($charge['date']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($charge['amount_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end">—</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($charge['paid_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($charge['balance_cents']) }}</flux:table.cell>
                                <flux:table.cell>{{ $charge['balance_cents'] === 0 && $charge['paid_on'] ? $longDate($charge['paid_on']) : '—' }}</flux:table.cell>
                                <flux:table.cell><x-status-pill :label="$charge['status']" :color="$charge['tone']" /></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                        @foreach ($account['months'] as $month)
                            <flux:table.row wire:key="soa-month-{{ $month['statement_id'] }}" :class="\Illuminate\Support\Arr::toCssClasses(['text-zinc-400' => ! $month['posted']])">
                                <flux:table.cell>{{ $month['month'] }}@if ($account['multiple_properties']) <span class="text-zinc-500">· {{ $month['property'] }}</span>@endif</flux:table.cell>
                                <flux:table.cell>{{ $longDate($month['deadline']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($month['dues_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">
                                    @if ($month['penalty_cents'] > 0)
                                        <span class="text-red-600 dark:text-red-400">{{ Soa::amount($month['penalty_cents']) }}</span> <span class="text-zinc-500">({{ $month['penalty_count'] }}×)</span>
                                    @elseif ($month['waived_cents'] > 0)
                                        <span class="text-zinc-500">{{ Soa::amount($month['waived_cents']) }} {{ __('waived') }}</span>
                                    @elseif ($month['pending_cents'] > 0)
                                        <span class="text-zinc-500">{{ Soa::amount($month['pending_cents']) }} {{ __('held') }}</span>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($month['paid_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($month['balance_cents']) }}</flux:table.cell>
                                <flux:table.cell>{{ $longDate($month['dues_paid_on']) }}</flux:table.cell>
                                <flux:table.cell><x-status-pill :label="$month['status']" :color="$month['tone']" /></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                        @if ($account['credit_cents'] > 0)
                            <flux:table.row>
                                <flux:table.cell colspan="5">{{ __('Advance payment / credit') }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums text-green-700 dark:text-green-400">−{{ Soa::amount($account['credit_cents']) }}</flux:table.cell>
                                <flux:table.cell></flux:table.cell>
                                <flux:table.cell><x-status-pill :label="__('Applied to next charges')" color="green" /></flux:table.cell>
                            </flux:table.row>
                        @endif
                        @if ($account['charges'] === [] && $account['months'] === [])
                            <flux:table.row><flux:table.cell colspan="8">{{ __('Nothing billed yet.') }}</flux:table.cell></flux:table.row>
                        @endif
                        <flux:table.row class="font-semibold">
                            <flux:table.cell colspan="2">{{ __('Totals as of :date', ['date' => $longDate($account['as_of'])]) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($account['billed_charges_cents'] + $account['billed_dues_cents']) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($account['billed_penalties_cents']) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($account['paid_cents'] + $account['discount_cents']) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $balance < 0 ? '−' : '' }}{{ Soa::amount($balance) }}</flux:table.cell>
                            <flux:table.cell colspan="2"></flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @elseif ($tab === 'ledger')
            <flux:card class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column>{{ __('Reference') }}</flux:table.column>
                        @if ($account['multiple_properties'])
                            <flux:table.column>{{ __('Property') }}</flux:table.column>
                        @endif
                        <flux:table.column align="end">{{ __('Charges') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Payments') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Running balance') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($account['lines'] as $line)
                            <flux:table.row wire:key="soa-line-{{ $loop->index }}">
                                <flux:table.cell>{{ $longDate($line['date']) }}</flux:table.cell>
                                <flux:table.cell :class="\Illuminate\Support\Arr::toCssClasses([
                                    'text-red-600 dark:text-red-400' => $line['kind'] === 'penalty',
                                    'text-green-700 dark:text-green-400' => in_array($line['kind'], ['waived', 'discount'], true),
                                ])">{{ $line['description'] }}</flux:table.cell>
                                <flux:table.cell>{{ $line['reference'] }}</flux:table.cell>
                                @if ($account['multiple_properties'])
                                    <flux:table.cell>{{ $line['property'] }}</flux:table.cell>
                                @endif
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($line['debit_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ Soa::amount($line['credit_cents']) }}</flux:table.cell>
                                <flux:table.cell align="end" :class="\Illuminate\Support\Arr::toCssClasses(['tabular-nums', 'text-green-700 dark:text-green-400' => $line['balance_cents'] < 0])">{{ $line['balance_cents'] < 0 ? '−' : '' }}{{ Soa::amount($line['balance_cents']) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="7">{{ __('No transactions on or before the as-of date.') }}</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @else
            <flux:card class="space-y-3">
                @forelse (array_reverse($account['payments']) as $payment)
                    <div wire:key="soa-payment-{{ $payment['id'] }}" class="border-t border-zinc-200 py-2 first:border-t-0 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="font-medium">{{ $payment['or_no'] }} · {{ $longDate($payment['paid_on']) }} · {{ $payment['method'] }}</span>
                            <span class="flex items-center gap-3">
                                <span class="tabular-nums">{{ Soa::peso($payment['amount_cents']) }}</span>
                                <flux:button size="sm" :href="route('receipts.print', $payment['id'])" target="_blank">{{ __('Receipt') }}</flux:button>
                            </span>
                        </div>
                        @if ($payment['applied_to'] !== [])
                            <flux:text size="sm">{{ __('Applied to') }}: {{ collect($payment['applied_to'])->map(fn ($applied) => $applied['label'].' '.Soa::amount($applied['cents']))->join(', ') }}</flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('No payments yet.') }}</flux:text>
                @endforelse
            </flux:card>
        @endif
    </div>
</div>
