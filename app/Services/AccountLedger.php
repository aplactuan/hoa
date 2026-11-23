<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PenaltyType;
use App\Models\AdvancePaymentEnrollment;
use App\Models\AdvancePaymentPromo;
use App\Models\Charge;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PenaltyAmnesty;
use App\Models\Property;
use App\Models\Setting;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes a homeowner's account by replaying everything that happened, in date order, up to an as-of date.
 *
 * Rules:
 *  - Charges (such as the one-time membership fee) post on the day they are raised and never carry a penalty.
 *  - Monthly dues post on the first day of their month and are due on the statement's due date (the due day of the following month).
 *  - Each month a month's dues stay unpaid after the due date plus grace days, that month gets one late penalty:
 *    a percent of that month's dues or a fixed amount, assessed again on the same day every following month.
 *  - Payments count from the date the owner paid. They settle principal first (charges and dues, oldest first)
 *    and penalties last (oldest first). Anything left over becomes advance credit for later charges.
 *  - Penalty amnesty: penalties assessed on or before the amnesty end date are held. Dues paid in full by then keep
 *    no penalty; dues still unpaid get all held penalties reinstated the day after the end date.
 *  - Advance payment promo: once payments made on or before the promo deadline cover everything billed through the
 *    last covered month (less the discount), the discount is credited on that day.
 */
class AccountLedger
{
    private const STATEMENT = 'statement';

    private const CHARGE = 'charge';

    /** @var array<string, array<string, mixed>> */
    private array $items = [];

    /** @var list<string> */
    private array $priority = [];

    /** @var list<array{source: string, cents: int, statements_only: bool}> */
    private array $creditPool = [];

    /** @var array<string, array<string, int>> cents keyed by source, then item key */
    private array $allocations = [];

    /** @var array<string, list<array{label: string, cents: int}>> */
    private array $applications = [];

    /** @var list<array<string, mixed>> */
    private array $lines = [];

    private int $runningBalance = 0;

    /**
     * @param  Collection<int, Property>  $properties
     * @param  CarbonInterface|null  $asOf  which charges, dues and payments are included (default today)
     * @param  CarbonInterface|null  $penaltiesUntil  how far penalties and amnesty lapses are counted (default the as-of date)
     * @return array<string, mixed>
     */
    public function build(Collection $properties, ?CarbonInterface $asOf = null, ?CarbonInterface $penaltiesUntil = null): array
    {
        $this->items = [];
        $this->priority = [];
        $this->creditPool = [];
        $this->allocations = [];
        $this->applications = [];
        $this->lines = [];
        $this->runningBalance = 0;

        $asOfDate = ($asOf ?? now())->toDateString();
        $penaltyDate = min(($penaltiesUntil ?? $asOf ?? now())->toDateString(), $asOfDate);
        $settings = Setting::current();
        $propertyIds = $properties->modelKeys();
        $multipleProperties = count($propertyIds) > 1;

        $statements = DuesStatement::query()->whereIn('property_id', $propertyIds)->with(['billingPeriod', 'adjustments', 'property'])->get();
        $charges = Charge::query()->whereIn('property_id', $propertyIds)->where('status', '!=', 'Refunded')->with('property')->get();
        $payments = Payment::query()->whereIn('property_id', $propertyIds)->where('status', '!=', 'Voided')
            ->with(['allocations', 'property'])->orderBy('paid_on')->orderBy('id')->get();
        $amnesties = PenaltyAmnesty::query()->where('is_active', true)->orderBy('ends_on')->get();

        foreach ($charges as $charge) {
            $this->addChargeItem($charge, $multipleProperties);
        }

        foreach ($statements as $statement) {
            $this->addStatementItem($statement, $settings, $multipleProperties);
        }

        $this->buildPriority();
        $inbound = $this->inboundAllocationCents($statements, $charges, $payments->modelKeys());
        $available = $this->availablePaymentCents($payments);
        $promos = $this->promoStatuses($properties, $statements, $charges, $payments, $asOfDate);

        foreach ($this->events($payments, $amnesties, $promos, $asOfDate, $penaltyDate) as $event) {
            match ($event['type']) {
                'post' => $this->post($event['key'], $event['date'], $inbound[$event['key']] ?? 0),
                'penalty' => $this->assessPenalty($event['key'], $event['date'], $amnesties),
                'lapse' => $this->lapseAmnesty($event['amnesty'], $event['date']),
                'payment' => $this->receivePayment($event['payment'], $event['date'], $available[$event['payment']->id]),
                'discount' => $this->applyDiscount($event['promo'], $event['date']),
            };
        }

        return $this->result($payments, $promos, $asOfDate, $penaltyDate);
    }

    private function addChargeItem(Charge $charge, bool $multipleProperties): void
    {
        $raisedOn = ($charge->created_at ?? now())->toDateString();
        $postOn = $charge->due_on !== null && $charge->due_on->toDateString() < $raisedOn ? $charge->due_on->toDateString() : $raisedOn;
        $waived = $charge->status === 'Waived';

        $this->items[$this->key($charge)] = $this->newItem([
            'type' => self::CHARGE,
            'model' => $charge,
            'post_date' => $postOn,
            'deadline' => $charge->due_on?->toDateString(),
            'label' => $charge->description.($multipleProperties ? ' · '.$charge->property->code : ''),
            'principal' => $waived ? 0 : BillingMath::cents($charge->amount),
            'billed' => BillingMath::cents($charge->amount),
            'waived_charge' => $waived,
        ]);
    }

    private function addStatementItem(DuesStatement $statement, Setting $settings, bool $multipleProperties): void
    {
        $period = CarbonImmutable::create($statement->billingPeriod->year, $statement->billingPeriod->month, 1);
        $adjustments = $statement->adjustments->where('status', 'Approved')->sum(fn ($adjustment): int => BillingMath::cents($adjustment->amount));
        $dues = BillingMath::cents($statement->regular) + BillingMath::cents($statement->special);
        $penaltyType = $statement->penalty_type ?? $settings->penalty_type;
        $penaltyRate = (string) ($statement->penalty_rate ?? ($penaltyType === PenaltyType::Fixed ? $settings->penalty_fixed_amount : $settings->penalty_percent));
        $perPenalty = $dues === 0 ? 0 : ($penaltyType === PenaltyType::Fixed ? BillingMath::cents($penaltyRate) : BillingMath::percentOf($dues, $penaltyRate));

        $this->items[$this->key($statement)] = $this->newItem([
            'type' => self::STATEMENT,
            'model' => $statement,
            'post_date' => $period->toDateString(),
            'deadline' => $statement->due_on->toDateString(),
            'month' => $period->format('M Y'),
            'label' => 'Dues – '.$period->format('M Y').($multipleProperties ? ' · '.$statement->property->code : ''),
            'principal' => max(0, $dues - $adjustments),
            'billed' => $dues,
            'adjustments' => $adjustments,
            'per_penalty' => $perPenalty,
            'grace_days' => $statement->grace_days ?? $settings->grace_days,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function newItem(array $attributes): array
    {
        return array_merge([
            'posted' => false,
            'principal_paid' => 0,
            'penalty' => 0,
            'penalty_paid' => 0,
            'penalty_count' => 0,
            'deferred' => [],
            'waived_cents' => 0,
            'pending_cents' => 0,
            'pending_count' => 0,
            'discount' => 0,
            'paid_date' => null,
            'previous_balance' => 0,
            'month' => null,
            'per_penalty' => 0,
            'grace_days' => 0,
            'adjustments' => 0,
            'waived_charge' => false,
        ], $attributes);
    }

    /**
     * Principal settles first (charges and dues, oldest first), then penalties (oldest month first).
     */
    private function buildPriority(): void
    {
        $keys = array_keys($this->items);
        $principal = $keys;
        usort($principal, fn (string $a, string $b): int => $this->principalOrder($a) <=> $this->principalOrder($b));
        $penalties = array_values(array_filter($principal, fn (string $key): bool => $this->items[$key]['type'] === self::STATEMENT));

        $this->priority = array_merge(
            array_map(fn (string $key): string => $key.'|principal', $principal),
            array_map(fn (string $key): string => $key.'|penalty', $penalties),
        );
    }

    /**
     * Oldest month first; within a month, charges such as the membership fee settle before that month's dues.
     *
     * @return array{0: string, 1: int, 2: string, 3: int}
     */
    private function principalOrder(string $key): array
    {
        $item = $this->items[$key];

        return [substr($item['post_date'], 0, 7), $item['type'] === self::CHARGE ? 0 : 1, $item['post_date'], $item['model']->id];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, PenaltyAmnesty>  $amnesties
     * @param  array<int, array<string, mixed>>  $promos
     * @return list<array<string, mixed>>
     */
    private function events(Collection $payments, Collection $amnesties, array $promos, string $asOfDate, string $penaltyDate): array
    {
        $events = [];

        foreach ($this->items as $key => $item) {
            if ($item['post_date'] <= $asOfDate) {
                $events[] = ['date' => $item['post_date'], 'order' => $item['type'] === self::CHARGE ? 0.0 : 1.0, 'seq' => $item['model']->id, 'type' => 'post', 'key' => $key];
            }

            if ($item['type'] !== self::STATEMENT || $item['per_penalty'] === 0) {
                continue;
            }

            $firstPenalty = CarbonImmutable::parse($item['deadline'])->addDays($item['grace_days'] + 1);

            for ($month = 0; $month < 600; $month++) {
                $date = $firstPenalty->addMonthsNoOverflow($month)->toDateString();

                if ($date > $penaltyDate) {
                    break;
                }

                $events[] = ['date' => $date, 'order' => 2.0, 'seq' => $item['model']->id, 'type' => 'penalty', 'key' => $key];
            }
        }

        foreach ($amnesties as $amnesty) {
            $lapsesOn = $amnesty->ends_on->addDay()->toDateString();

            if ($lapsesOn <= $penaltyDate) {
                $events[] = ['date' => $lapsesOn, 'order' => 0.5, 'seq' => $amnesty->id, 'type' => 'lapse', 'amnesty' => $amnesty];
            }
        }

        foreach ($payments as $payment) {
            if ($payment->paid_on->toDateString() <= $asOfDate) {
                $events[] = ['date' => $payment->paid_on->toDateString(), 'order' => 3.0, 'seq' => $payment->id, 'type' => 'payment', 'payment' => $payment];
            }
        }

        foreach ($promos as $promo) {
            if ($promo['qualified_on'] !== null) {
                $events[] = ['date' => $promo['qualified_on'], 'order' => 3.5, 'seq' => $promo['promo']->id, 'type' => 'discount', 'promo' => $promo];
            }
        }

        usort($events, fn (array $a, array $b): int => [$a['date'], $a['order'], $a['seq']] <=> [$b['date'], $b['order'], $b['seq']]);

        return $events;
    }

    private function post(string $key, string $date, int $inboundCents): void
    {
        $this->items[$key]['previous_balance'] = max(0, $this->openTotal() - array_sum(array_column($this->creditPool, 'cents')));
        $item = &$this->items[$key];
        $item['posted'] = true;
        $item['principal_paid'] = min($item['principal'], $inboundCents);

        if ($item['type'] === self::CHARGE) {
            $this->line($date, $item['model']->description.($item['waived_charge'] ? ' (waived)' : ''), $item['model']->code, $item['model']->property->code, $item['principal'], 0, 'charge');
        } elseif ($item['principal'] > 0) {
            $this->line($date, 'Monthly dues – '.$item['month'].' (due '.$this->longDate($item['deadline']).')', $item['model']->code, $item['model']->property->code, $item['principal'], 0, 'dues');
        }

        unset($item);
        $this->applyCredit($date);
    }

    /**
     * @param  Collection<int, PenaltyAmnesty>  $amnesties
     */
    private function assessPenalty(string $key, string $date, Collection $amnesties): void
    {
        $item = &$this->items[$key];

        if (! $item['posted'] || $item['principal'] - $item['principal_paid'] <= 0) {
            return;
        }

        $amnesty = $amnesties->first(fn (PenaltyAmnesty $amnesty): bool => $date <= $amnesty->ends_on->toDateString());

        if ($amnesty !== null) {
            $item['deferred'][$amnesty->id] = ($item['deferred'][$amnesty->id] ?? 0) + 1;

            return;
        }

        $item['penalty'] += $item['per_penalty'];
        $item['penalty_count']++;
        $this->line($date, 'Late penalty – '.$item['month'].' dues ('.$this->ordinal($item['penalty_count']).' month late)', $item['model']->code, $item['model']->property->code, $item['per_penalty'], 0, 'penalty');
        unset($item);
        $this->applyCredit($date);
    }

    private function lapseAmnesty(PenaltyAmnesty $amnesty, string $date): void
    {
        foreach ($this->items as $key => $item) {
            $months = $item['deferred'][$amnesty->id] ?? 0;

            if ($months === 0) {
                continue;
            }

            unset($this->items[$key]['deferred'][$amnesty->id]);
            $amount = $months * $item['per_penalty'];
            $plural = $months > 1 ? 's' : '';

            if ($item['principal'] - $item['principal_paid'] > 0) {
                $this->items[$key]['penalty'] += $amount;
                $this->items[$key]['penalty_count'] += $months;
                $this->line($date, 'Penalties reinstated – '.$item['month'].' dues ('.$months.' month'.$plural.' late, not paid by '.$this->longDate($amnesty->ends_on->toDateString()).')', $item['model']->code, $item['model']->property->code, $amount, 0, 'penalty');
            } else {
                $this->items[$key]['waived_cents'] += $amount;
                $this->line($date, 'Penalty waived – '.$item['month'].' dues paid by '.$this->longDate($amnesty->ends_on->toDateString()).' ('.BillingMath::decimal($amount).' waived)', $item['model']->code, $item['model']->property->code, 0, 0, 'waived');
            }
        }

        $this->applyCredit($date);
    }

    private function receivePayment(Payment $payment, string $date, int $availableCents): void
    {
        $source = 'payment:'.$payment->id;
        $left = $this->allocate($availableCents, $date, $source, false);

        if ($left > 0) {
            $this->creditPool[] = ['source' => $source, 'cents' => $left, 'statements_only' => false];
            $this->applications[$source][] = ['label' => 'Advance / credit', 'cents' => $left];
        }

        $this->line($date, 'Payment received', $payment->or_no, $payment->property->code ?? '', 0, BillingMath::cents($payment->amount), 'payment');
    }

    /**
     * @param  array<string, mixed>  $promo
     */
    private function applyDiscount(array $promo, string $date): void
    {
        $source = 'discount:'.$promo['promo']->id;
        $left = $this->allocate($promo['discount_cents'], $date, $source, true);

        if ($left > 0) {
            $this->creditPool[] = ['source' => $source, 'cents' => $left, 'statements_only' => true];
        }

        $this->line($date, 'Early payment discount – '.$promo['promo']->name.' (paid by '.$this->longDate($promo['promo']->pay_by->toDateString()).')', '', '', 0, $promo['discount_cents'], 'discount');
    }

    private function applyCredit(string $date): void
    {
        foreach ($this->creditPool as $index => $credit) {
            $this->creditPool[$index]['cents'] = $this->allocate($credit['cents'], $date, $credit['source'], $credit['statements_only'], false);
        }

        $this->creditPool = array_values(array_filter($this->creditPool, fn (array $credit): bool => $credit['cents'] > 0));
    }

    /**
     * @param  bool  $describe  whether to list what this money settled under its source, as shown beside a payment when it is received
     */
    private function allocate(int $amount, string $date, string $source, bool $statementsOnly, bool $describe = true): int
    {
        foreach ($this->priority as $entry) {
            if ($amount === 0) {
                break;
            }

            [$key, $part] = explode('|', $entry);
            $item = &$this->items[$key];

            if (! $item['posted'] || ($statementsOnly && $item['type'] !== self::STATEMENT)) {
                unset($item);

                continue;
            }

            $open = $part === 'principal' ? $item['principal'] - $item['principal_paid'] : $item['penalty'] - $item['penalty_paid'];
            $applied = min($open, $amount);

            if ($applied <= 0) {
                unset($item);

                continue;
            }

            $amount -= $applied;
            $this->allocations[$source][$key] = ($this->allocations[$source][$key] ?? 0) + $applied;

            if ($part === 'principal') {
                $item['principal_paid'] += $applied;

                if ($item['principal_paid'] === $item['principal']) {
                    $item['paid_date'] = $date;
                }
            } else {
                $item['penalty_paid'] += $applied;
            }

            if (str_starts_with($source, 'discount:')) {
                $item['discount'] += $applied;
            }

            if ($describe) {
                $this->applications[$source][] = ['label' => $part === 'principal' ? $item['label'] : 'Penalty – '.$item['month'], 'cents' => $applied];
            }

            unset($item);
        }

        return $amount;
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @param  Collection<int, DuesStatement>  $statements
     * @param  Collection<int, Charge>  $charges
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function promoStatuses(Collection $properties, Collection $statements, Collection $charges, Collection $payments, string $asOfDate): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        $billingPropertyId = $properties->min('id');
        $memberSince = Owner::query()->whereIn('property_id', $properties->modelKeys())->where('status', 'Current')->min('started_on');
        $statuses = [];

        $promos = AdvancePaymentPromo::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereHas('enrollments', fn (Builder $enrollments) => $enrollments->whereIn('property_id', $properties->modelKeys())))
            ->orderBy('pay_by')
            ->get();

        foreach ($promos as $promo) {
            $coversTo = $promo->covers_to->endOfMonth()->toDateString();
            $eligible = $memberSince === null || CarbonImmutable::parse($memberSince)->toDateString() <= $promo->covers_from->endOfMonth()->toDateString();
            $lockedFee = AdvancePaymentEnrollment::query()->where('advance_payment_promo_id', $promo->id)->whereIn('property_id', $properties->modelKeys())->value('monthly_fee');
            $monthlyFee = BillingMath::cents((string) ($lockedFee ?? MonthlyFeeRate::amountFor($promo->covers_from->year, $promo->covers_from->month)));

            $billed = $charges->where('status', '!=', 'Waived')
                ->filter(fn (Charge $charge): bool => $this->items[$this->key($charge)]['post_date'] <= $coversTo)
                ->sum(fn (Charge $charge): int => BillingMath::cents($charge->amount));
            $coveredDues = 0;

            foreach ($statements as $statement) {
                $item = $this->items[$this->key($statement)];

                if ($item['post_date'] <= $coversTo) {
                    $billed += $item['principal'];
                }

                if ($promo->covers($statement->billingPeriod->year, $statement->billingPeriod->month)) {
                    $coveredDues += $item['principal'];
                }
            }

            foreach ($promo->coveredMonths() as $month) {
                $generated = $statements->contains(fn (DuesStatement $statement): bool => $statement->property_id === $billingPropertyId
                    && $statement->billingPeriod->year === $month['year'] && $statement->billingPeriod->month === $month['month']);

                if (! $generated) {
                    $billed += $monthlyFee;
                    $coveredDues += $monthlyFee;
                }
            }

            $discount = $promo->discount_type === DiscountType::Percent ? BillingMath::percentOf($coveredDues, $promo->discount_value) : BillingMath::cents($promo->discount_value);
            $required = max(0, $billed - $discount);
            $paidToward = 0;
            $qualifiedOn = null;
            $qualifyingPaymentId = null;
            $deadline = min($promo->pay_by->toDateString(), $asOfDate);

            foreach ($payments as $payment) {
                if ($payment->paid_on->toDateString() > $deadline) {
                    break;
                }

                $paidToward += BillingMath::cents($payment->amount);

                if ($eligible && $qualifiedOn === null && $paidToward >= $required && $discount > 0) {
                    $qualifiedOn = $payment->paid_on->toDateString();
                    $qualifyingPaymentId = $payment->id;
                }
            }

            $statuses[$promo->id] = [
                'promo' => $promo,
                'eligible' => $eligible,
                'required_cents' => $required,
                'paid_toward_cents' => $paidToward,
                'remaining_cents' => max(0, $required - $paidToward),
                'discount_cents' => $discount,
                'monthly_fee_cents' => $monthlyFee,
                'qualified_on' => $qualifiedOn,
                'qualifying_payment_id' => $qualifyingPaymentId,
            ];
        }

        return $statuses;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  array<int, array<string, mixed>>  $promos
     * @return array<string, mixed>
     */
    private function result(Collection $payments, array $promos, string $asOfDate, string $penaltyDate): array
    {
        $months = [];
        $charges = [];
        $totals = ['billed_charges' => 0, 'billed_dues' => 0, 'billed_penalties' => 0, 'overdue' => 0, 'not_yet_due' => 0, 'pending_penalties' => 0, 'waived_penalties' => 0];
        $nextDue = null;

        foreach ($this->items as $key => $item) {
            foreach ($item['deferred'] as $count) {
                if ($item['principal'] - $item['principal_paid'] <= 0) {
                    $this->items[$key]['waived_cents'] += $count * $item['per_penalty'];
                } else {
                    $this->items[$key]['pending_cents'] += $count * $item['per_penalty'];
                    $this->items[$key]['pending_count'] += $count;
                }
            }

            $item = $this->items[$key];
            $balance = $item['posted'] ? ($item['principal'] - $item['principal_paid']) + ($item['penalty'] - $item['penalty_paid']) : 0;
            $totals['waived_penalties'] += $item['waived_cents'];
            $totals['pending_penalties'] += $item['pending_cents'];

            if ($item['type'] === self::CHARGE) {
                if ($item['posted']) {
                    $totals['billed_charges'] += $item['principal'];
                }

                $charges[] = $this->chargeRow($item, $balance);

                continue;
            }

            if ($item['posted']) {
                $totals['billed_dues'] += $item['principal'];
                $totals['billed_penalties'] += $item['penalty'];

                if ($balance > 0 && $penaltyDate > $item['deadline']) {
                    $totals['overdue'] += $balance;
                } elseif ($balance > 0) {
                    $totals['not_yet_due'] += $balance;
                    $nextDue = $nextDue === null || $item['deadline'] < $nextDue ? $item['deadline'] : $nextDue;
                }
            }

            if ($item['billed'] > 0 || $item['penalty'] > 0) {
                $months[] = $this->monthRow($key, $item, $balance, $penaltyDate);
            }
        }

        usort($months, fn (array $a, array $b): int => [$a['post_date'], $a['property']] <=> [$b['post_date'], $b['property']]);
        usort($charges, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        $includedPayments = $payments->filter(fn (Payment $payment): bool => $payment->paid_on->toDateString() <= $asOfDate)->values();
        $paid = $includedPayments->sum(fn (Payment $payment): int => BillingMath::cents($payment->amount));
        $discount = collect($this->lines)->where('kind', 'discount')->sum('credit_cents');
        $credit = array_sum(array_column($this->creditPool, 'cents'));
        $billed = $totals['billed_charges'] + $totals['billed_dues'] + $totals['billed_penalties'];

        return [
            'as_of' => $asOfDate,
            'months' => $months,
            'charges' => $charges,
            'lines' => $this->lines,
            'payments' => $includedPayments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'or_no' => $payment->or_no,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount_cents' => BillingMath::cents($payment->amount),
                'method' => $payment->method,
                'status' => $payment->status,
                'applied_to' => $this->applications['payment:'.$payment->id] ?? [],
            ])->all(),
            'future_payments' => $payments->count() - $includedPayments->count(),
            'promos' => array_values(array_map(fn (array $promo): array => array_merge($promo, ['qualified' => $promo['qualified_on'] !== null]), $promos)),
            'allocations' => $this->allocations,
            'statement_state' => collect($this->items)->where('type', self::STATEMENT)->mapWithKeys(fn (array $item): array => [$item['model']->id => [
                'penalty_cents' => $item['penalty'],
                'discount_cents' => $item['discount'],
                'previous_balance_cents' => $item['previous_balance'],
            ]])->all(),
            'billed_charges_cents' => $totals['billed_charges'],
            'billed_dues_cents' => $totals['billed_dues'],
            'billed_penalties_cents' => $totals['billed_penalties'],
            'billed_cents' => $billed,
            'paid_cents' => $paid,
            'discount_cents' => $discount,
            'credit_cents' => $credit,
            'balance_cents' => $billed - $paid - $discount,
            'overdue_cents' => $totals['overdue'],
            'not_yet_due_cents' => $totals['not_yet_due'],
            'next_due_on' => $nextDue,
            'pending_penalties_cents' => $totals['pending_penalties'],
            'waived_penalties_cents' => $totals['waived_penalties'],
            'penalty_months' => collect($this->items)->sum('penalty_count'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function monthRow(string $key, array $item, int $balance, string $asOfDate): array
    {
        [$status, $tone] = $this->monthStatus($item, $asOfDate);

        return [
            'key' => $key,
            'statement_id' => $item['model']->id,
            'code' => $item['model']->code,
            'month' => $item['month'],
            'property' => $item['model']->property->code,
            'post_date' => $item['post_date'],
            'deadline' => $item['deadline'],
            'posted' => $item['posted'],
            'dues_cents' => $item['principal'],
            'penalty_cents' => $item['penalty'],
            'penalty_count' => $item['penalty_count'],
            'waived_cents' => $item['waived_cents'],
            'pending_cents' => $item['pending_cents'],
            'discount_cents' => $item['discount'],
            'paid_cents' => $item['principal_paid'] + $item['penalty_paid'],
            'balance_cents' => $balance,
            'dues_paid_on' => $item['posted'] && $item['principal'] === $item['principal_paid'] ? $item['paid_date'] : null,
            'status' => $status,
            'tone' => $tone,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function chargeRow(array $item, int $balance): array
    {
        [$status, $tone] = match (true) {
            ! $item['posted'] => ['Upcoming', 'zinc'],
            $item['waived_charge'] => ['Waived', 'green'],
            $balance === 0 => ['Paid', 'green'],
            default => ['Unpaid · no penalty', 'sky'],
        };

        return [
            'code' => $item['model']->code,
            'label' => $item['label'],
            'date' => $item['post_date'],
            'deadline' => $item['deadline'],
            'amount_cents' => $item['billed'],
            'paid_cents' => $item['principal_paid'],
            'balance_cents' => $balance,
            'paid_on' => $item['paid_date'],
            'status' => $status,
            'tone' => $tone,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: string, 1: string}
     */
    private function monthStatus(array $item, string $asOfDate): array
    {
        if (! $item['posted']) {
            return ['Upcoming', 'zinc'];
        }

        if ($item['principal'] - $item['principal_paid'] <= 0) {
            return match (true) {
                $item['penalty'] - $item['penalty_paid'] > 0 => ['Dues paid · penalty unpaid', 'red'],
                $item['waived_cents'] > 0 => ['Paid · penalty waived', 'green'],
                $item['penalty_count'] > 0 => ['Paid late', 'amber'],
                default => ['Paid on time', 'green'],
            };
        }

        if ($asOfDate <= $item['deadline']) {
            return ['Due by '.CarbonImmutable::parse($item['deadline'])->format('M j'), 'sky'];
        }

        return ['Overdue · '.($item['penalty_count'] + $item['pending_count']).' mo', 'red'];
    }

    private function line(string $date, string $description, string $reference, string $property, int $debit, int $credit, string $kind): void
    {
        $this->runningBalance += $debit - $credit;
        $this->lines[] = [
            'date' => $date,
            'description' => $description,
            'reference' => $reference,
            'property' => $property,
            'debit_cents' => $debit,
            'credit_cents' => $credit,
            'balance_cents' => $this->runningBalance,
            'kind' => $kind,
        ];
    }

    private function openTotal(): int
    {
        return array_sum(array_map(fn (array $item): int => $item['posted'] ? ($item['principal'] - $item['principal_paid']) + ($item['penalty'] - $item['penalty_paid']) : 0, $this->items));
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<int, int>
     */
    private function availablePaymentCents(Collection $payments): array
    {
        $available = [];

        foreach ($payments as $payment) {
            $outbound = $payment->allocations
                ->reject(fn (PaymentAllocation $allocation): bool => isset($this->items[$allocation->allocatable_type.':'.$allocation->allocatable_id]))
                ->sum(fn (PaymentAllocation $allocation): int => BillingMath::cents($allocation->amount));
            $available[$payment->id] = max(0, BillingMath::cents($payment->amount) - $outbound);
        }

        return $available;
    }

    /**
     * Amounts paid into this account's items by payments recorded outside it, such as before a property changed hands.
     *
     * @param  Collection<int, DuesStatement>  $statements
     * @param  Collection<int, Charge>  $charges
     * @param  list<int>  $paymentIds
     * @return array<string, int>
     */
    private function inboundAllocationCents(Collection $statements, Collection $charges, array $paymentIds): array
    {
        return PaymentAllocation::query()
            ->whereNotIn('payment_id', $paymentIds)
            ->whereHas('payment', fn (Builder $query) => $query->where('status', '!=', 'Voided'))
            ->where(function (Builder $query) use ($statements, $charges): void {
                $query->where(fn (Builder $inner) => $inner->where('allocatable_type', (new DuesStatement)->getMorphClass())->whereIn('allocatable_id', $statements->modelKeys()))
                    ->orWhere(fn (Builder $inner) => $inner->where('allocatable_type', (new Charge)->getMorphClass())->whereIn('allocatable_id', $charges->modelKeys()));
            })
            ->get()
            ->groupBy(fn (PaymentAllocation $allocation): string => $allocation->allocatable_type.':'.$allocation->allocatable_id)
            ->map(fn ($allocations): int => $allocations->sum(fn (PaymentAllocation $allocation): int => BillingMath::cents($allocation->amount)))
            ->all();
    }

    public function key(DuesStatement|Charge $model): string
    {
        return $model->getMorphClass().':'.$model->getKey();
    }

    private function longDate(string $date): string
    {
        return CarbonImmutable::parse($date)->format('M j, Y');
    }

    private function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }
}
