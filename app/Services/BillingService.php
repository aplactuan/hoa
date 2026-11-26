<?php

namespace App\Services;

use App\Actions\ChargeMembershipFee;
use App\Enums\PenaltyType;
use App\Enums\RoleName;
use App\Models\AdvancePaymentEnrollment;
use App\Models\BillingPeriod;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\StatementAdjustment;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BillingService
{
    public function __construct(
        private DisplayCode $codes,
        private AccountService $accounts,
        private OfficeAlerts $alerts,
        private OwnerAccounts $owners,
        private AccountRecalculator $recalculator,
    ) {}

    /**
     * Bill every property for a month. The flat monthly fee is charged once per owner, on the owner's billing property.
     */
    public function generatePeriod(int $year, int $month, ?int $userId = null): int
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid billing period.');
        }

        $period = BillingPeriod::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['generated_at' => now(), 'generated_by' => $userId],
        );
        $settings = Setting::current();
        $monthlyFee = MonthlyFeeRate::amountFor($year, $month);
        $count = 0;

        foreach ($this->owners->allGroups() as $propertyIds) {
            DB::transaction(function () use ($propertyIds, $period, $settings, $monthlyFee, $userId, &$count): void {
                $properties = Property::query()->whereKey($propertyIds)->orderBy('id')->lockForUpdate()->get();
                $created = $this->billGroup($properties, $period, $settings, $monthlyFee, $this->enrollmentsFor($propertyIds), $userId);
                $count += count($created);

                if ($created !== []) {
                    $this->recalculator->recalculate($properties, 'statement generation', $created);
                }
            });
        }

        return $count;
    }

    /**
     * Creates the statements an owner group is missing for one billing period, without recalculating the account.
     *
     * @param  EloquentCollection<int, Property>  $properties  one owner group, ordered by id so the first is the billing property
     * @param  EloquentCollection<int, AdvancePaymentEnrollment>  $enrollments  the group's promo enrollments
     * @return list<int> ids of the statements created
     */
    private function billGroup(EloquentCollection $properties, BillingPeriod $period, Setting $settings, string $monthlyFee, EloquentCollection $enrollments, ?int $userId): array
    {
        $billingPropertyId = $properties->first()?->id;
        $enrollment = $this->enrollmentCovering($enrollments, $period->year, $period->month);
        $billed = DuesStatement::query()->whereIn('property_id', $properties->modelKeys())->where('billing_period_id', $period->id)->pluck('property_id')->all();
        $created = [];

        foreach ($properties as $property) {
            if (in_array($property->id, $billed, true)) {
                continue;
            }

            $regular = $property->id === $billingPropertyId ? BillingMath::cents($enrollment?->monthly_fee ?? $monthlyFee) : 0;

            $created[] = $property->statements()->create([
                'code' => $this->codes->next('statement', sprintf('%04d%02d', $period->year, $period->month)),
                'billing_period_id' => $period->id,
                'regular' => BillingMath::decimal($regular),
                'special' => '0.00',
                'penalty' => '0.00',
                'penalty_type' => $settings->penalty_type,
                'penalty_rate' => $this->penaltyRate($settings),
                'grace_days' => $settings->grace_days,
                'previous_balance' => '0.00',
                'credits' => '0.00',
                'due_on' => $this->dueOn($settings, $period->year, $period->month),
                'created_by' => $userId,
            ])->id;
        }

        return $created;
    }

    /**
     * Rebuilds every account from the current billing rules, starting at the given month.
     *
     * Bills any month from $from to now that has no statement yet, reprices the statements from $from on with today's
     * monthly fee and penalty rules, charges or reprices each member's one-time membership fee dated from their start of
     * ownership, then recalculates penalties and payment allocations. Running it again with the same rules changes nothing.
     *
     * Each owner group is rebuilt and committed in its own transaction and recalculated once, so an interrupted run can
     * simply be started again.
     *
     * @param  (Closure(int, int): void)|null  $progress  called with the number of owner groups done and the total
     * @return array{statements_created: int, statements_repriced: int, membership_fees_created: int, membership_fees_updated: int, accounts_recalculated: int}
     */
    public function rebuildAccounts(CarbonImmutable $from, ?string $monthlyFee = null, ?int $userId = null, ?Closure $progress = null): array
    {
        $from = $from->startOfMonth();

        if ($monthlyFee !== null) {
            MonthlyFeeRate::query()->whereDate('effective_from', $from->toDateString())->firstOrNew()
                ->fill(['effective_from' => $from->toDateString(), 'amount' => $monthlyFee, 'remarks' => 'Set by account rebuild', 'created_by' => $userId])
                ->save();
        }

        /** @var array<string, array{period: BillingPeriod, fee: string}> $periods */
        $periods = [];

        for ($month = $from; $month->lessThanOrEqualTo(now()->startOfMonth()); $month = $month->addMonthNoOverflow()) {
            $periods[$month->year.'-'.$month->month] = [
                'period' => BillingPeriod::query()->firstOrCreate(['year' => $month->year, 'month' => $month->month], ['generated_at' => now(), 'generated_by' => $userId]),
                'fee' => MonthlyFeeRate::amountFor($month->year, $month->month),
            ];
        }

        $settings = Setting::current();
        $groups = $this->owners->allGroups();
        $totals = ['statements_created' => 0, 'statements_repriced' => 0, 'membership_fees_created' => 0, 'membership_fees_updated' => 0, 'accounts_recalculated' => 0];
        $progress?->__invoke(0, count($groups));

        foreach ($groups as $propertyIds) {
            DB::transaction(function () use ($propertyIds, $periods, $from, $settings, $userId, &$totals): void {
                $properties = Property::query()->whereKey($propertyIds)->orderBy('id')->lockForUpdate()->get();
                $enrollments = $this->enrollmentsFor($propertyIds);

                foreach ($periods as $billing) {
                    $totals['statements_created'] += count($this->billGroup($properties, $billing['period'], $settings, $billing['fee'], $enrollments, $userId));
                }

                $totals['statements_repriced'] += $this->repriceStatements($properties, $from, $settings, $enrollments, array_map(fn (array $billing): string => $billing['fee'], $periods));

                $membership = $this->rebuildMembershipFee($properties, $settings, $userId);

                if ($membership !== null) {
                    $totals['membership_fees_'.$membership]++;
                }

                $this->recalculator->recalculate($properties, 'account rebuild', [], false);
                $totals['accounts_recalculated']++;
            });

            $progress?->__invoke($totals['accounts_recalculated'], count($groups));
        }

        return $totals;
    }

    /** @param array{amount: string, method: string, paid_on: string, reference_no?: string|null, remarks?: string|null} $attributes */
    public function recordPayment(Property $property, array $attributes, ?int $userId = null): Payment
    {
        $amount = BillingMath::cents($attributes['amount']);

        if ($amount <= 0 || $attributes['paid_on'] > now()->toDateString()) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be positive and the date cannot be in the future.']);
        }

        if ($attributes['method'] !== 'Cash' && empty($attributes['reference_no'])) {
            throw ValidationException::withMessages(['reference_no' => 'A reference number is required for non-cash payments.']);
        }

        if ($attributes['paid_on'] < now()->subDays(30)->toDateString() && blank($attributes['remarks'] ?? null)) {
            throw ValidationException::withMessages(['remarks' => 'Explain why a payment made more than 30 days ago is only being recorded now.']);
        }

        return DB::transaction(function () use ($property, $attributes, $userId, $amount): Payment {
            Property::query()->lockForUpdate()->findOrFail($property->id);

            $payment = $property->payments()->create([
                'code' => $this->codes->next('payment'),
                'or_no' => $this->codes->next('official_receipt'),
                'paid_on' => $attributes['paid_on'],
                'amount' => BillingMath::decimal($amount),
                'method' => $attributes['method'],
                'reference_no' => $attributes['reference_no'] ?? null,
                'remarks' => $attributes['remarks'] ?? null,
                'status' => 'Posted',
                'received_by' => $userId,
            ]);

            $this->recalculateGroup($property, sprintf('payment %s dated %s recorded', $payment->or_no, $payment->paid_on->toDateString()));

            return $payment;
        });
    }

    public function recalculateGroup(Property $property, string $reason, bool $noteChanges = true): void
    {
        $this->recalculator->recalculate($this->owners->groupProperties($property), $reason, [], $noteChanges);
    }

    /**
     * Brings every account up to date. Late penalties accrue monthly and amnesties lapse on a date, so this runs daily.
     */
    public function recalculateAll(): int
    {
        $count = 0;

        foreach ($this->owners->allGroups() as $propertyIds) {
            DB::transaction(function () use ($propertyIds): void {
                $properties = Property::query()->whereKey($propertyIds)->orderBy('id')->lockForUpdate()->get();
                $this->recalculator->recalculate($properties, 'daily update', [], false);
            });
            $count++;
        }

        return $count;
    }

    public function requestVoid(Payment $payment, string $reason, int $userId): void
    {
        if ($payment->status !== 'Posted' || trim($reason) === '') {
            throw ValidationException::withMessages(['void_reason' => 'A posted payment and reason are required.']);
        }

        $payment->update(['status' => 'Void Requested', 'void_reason' => $reason, 'void_requested_by' => $userId]);
        $this->alerts->send([RoleName::Administrator], 'Payment void requested', $payment->code.' awaits approval.', 'receipts.index');
    }

    public function approveVoid(Payment $payment, int $administratorId): void
    {
        if ($payment->status !== 'Void Requested') {
            throw ValidationException::withMessages(['status' => 'Only requested voids can be approved.']);
        }

        DB::transaction(function () use ($payment, $administratorId): void {
            $payment->update(['status' => 'Voided', 'voided_by' => $administratorId]);
            $this->recalculateGroup($payment->property, sprintf('payment %s voided', $payment->or_no));
        });
    }

    public function requestAdjustment(DuesStatement $statement, string $amount, string $reason, int $userId): StatementAdjustment
    {
        if (BillingMath::cents($amount) <= 0 || BillingMath::cents($amount) > $this->accounts->statementBalanceCents($statement) || trim($reason) === '') {
            throw ValidationException::withMessages(['amount' => 'A positive amount within the open balance and a reason are required.']);
        }

        $adjustment = $statement->adjustments()->create([
            'amount' => $amount,
            'kind' => 'Credit',
            'reason' => $reason,
            'requested_by' => $userId,
            'status' => 'Pending',
        ]);
        $this->alerts->send([RoleName::Administrator], 'Statement adjustment requested', $statement->code.' awaits approval.', 'statements.index');

        return $adjustment;
    }

    public function approveAdjustment(StatementAdjustment $adjustment, int $administratorId): void
    {
        if ($adjustment->status !== 'Pending') {
            throw ValidationException::withMessages(['status' => 'Only pending adjustments can be approved.']);
        }

        if (BillingMath::cents($adjustment->amount) > $this->accounts->statementBalanceCents($adjustment->statement)) {
            throw ValidationException::withMessages(['amount' => 'The adjustment exceeds the current open balance.']);
        }

        DB::transaction(function () use ($adjustment, $administratorId): void {
            $adjustment->update(['status' => 'Approved', 'approved_by' => $administratorId, 'approved_at' => now()]);
            $this->recalculateGroup($adjustment->statement->property, 'adjustment approved');
        });
    }

    public function createCharge(Property $property, string $description, string $amount, ?string $dueOn = null, ?int $userId = null, ?int $chargeTypeId = null): Charge
    {
        if (BillingMath::cents($amount) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Charge amount must be positive.']);
        }

        return DB::transaction(function () use ($property, $description, $amount, $dueOn, $userId, $chargeTypeId): Charge {
            $charge = $property->charges()->create([
                'code' => $this->codes->next('charge'),
                'charge_type_id' => $chargeTypeId,
                'description' => $description,
                'amount' => $amount,
                'due_on' => $dueOn,
                'status' => 'Unpaid',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->recalculateGroup($property, 'charge '.$charge->code.' added');

            return $charge;
        });
    }

    public function requestWaiver(Charge $charge, string $reason, int $userId): void
    {
        if ($this->accounts->chargeBalanceCents($charge) <= 0 || $charge->allocations()->exists() || trim($reason) === '') {
            throw ValidationException::withMessages(['waiver_reason' => 'A fully unpaid charge and reason are required.']);
        }

        $charge->update(['status' => 'Waiver Requested', 'waiver_reason' => $reason, 'waiver_requested_by' => $userId]);
        $this->alerts->send([RoleName::Administrator], 'Charge waiver requested', $charge->code.' awaits approval.', 'charges.index');
    }

    public function approveWaiver(Charge $charge, int $administratorId): void
    {
        if ($charge->status !== 'Waiver Requested') {
            throw ValidationException::withMessages(['status' => 'Only requested waivers can be approved.']);
        }

        if ($charge->allocations()->exists()) {
            throw ValidationException::withMessages(['status' => 'A charge with payments cannot be waived.']);
        }

        DB::transaction(function () use ($charge, $administratorId): void {
            $charge->update(['status' => 'Waived', 'waived_by' => $administratorId]);
            $this->recalculateGroup($charge->property, 'charge waived');
        });
    }

    /**
     * Applies the current monthly fee and penalty rules to an owner group's statements from the given month on.
     *
     * @param  EloquentCollection<int, Property>  $properties  one owner group, ordered by id so the first is the billing property
     * @param  EloquentCollection<int, AdvancePaymentEnrollment>  $enrollments  the group's promo enrollments
     * @param  array<string, string>  $monthlyFees  monthly fee keyed by "year-month", looked up when missing
     */
    private function repriceStatements(EloquentCollection $properties, CarbonImmutable $from, Setting $settings, EloquentCollection $enrollments, array $monthlyFees): int
    {
        $billingPropertyId = $properties->first()?->id;
        $statements = DuesStatement::query()
            ->whereIn('property_id', $properties->modelKeys())
            ->whereHas('billingPeriod', fn (Builder $query) => $query
                ->where('year', '>', $from->year)
                ->orWhere(fn (Builder $query) => $query->where('year', $from->year)->where('month', '>=', $from->month)))
            ->with('billingPeriod')
            ->get();
        $repriced = 0;

        foreach ($statements as $statement) {
            $year = $statement->billingPeriod->year;
            $month = $statement->billingPeriod->month;
            $regular = $statement->property_id === $billingPropertyId
                ? BillingMath::cents($this->enrollmentCovering($enrollments, $year, $month)?->monthly_fee ?? $monthlyFees[$year.'-'.$month] ?? MonthlyFeeRate::amountFor($year, $month))
                : 0;

            $statement->fill([
                'regular' => BillingMath::decimal($regular),
                'penalty_type' => $settings->penalty_type,
                'penalty_rate' => $this->penaltyRate($settings),
                'grace_days' => $settings->grace_days,
                'due_on' => $this->dueOn($settings, $year, $month),
            ]);

            if ($statement->isDirty()) {
                $statement->save();
                $repriced++;
            }
        }

        return $repriced;
    }

    /**
     * Charges the one-time membership fee for an owner group, or brings an existing one in line with the current fee.
     *
     * The fee is due on the group's earliest start of ownership. A member whose portal account already has a membership
     * fee on another property, including one they used to own, is not charged again. A waived fee is left alone.
     *
     * @param  EloquentCollection<int, Property>  $properties  one owner group, ordered by id so the first is the billing property
     * @return 'created'|'updated'|null
     */
    private function rebuildMembershipFee(EloquentCollection $properties, Setting $settings, ?int $userId): ?string
    {
        $owners = Owner::query()->whereIn('property_id', $properties->modelKeys())->where('status', 'Current')->get();

        if ($owners->isEmpty()) {
            return null;
        }

        $dueOn = $owners->min('started_on')->toDateString();
        $memberPropertyIds = Owner::withTrashed()
            ->whereIn('user_id', $owners->pluck('user_id')->filter()->unique())
            ->pluck('property_id')
            ->merge($properties->modelKeys())
            ->unique();
        $chargeTypeId = ChargeType::query()->where('name', 'Membership Fee')->value('id');
        $existing = Charge::query()
            ->whereIn('property_id', $memberPropertyIds)
            ->where('status', '!=', 'Refunded')
            ->where(fn (Builder $query) => $query
                ->where('description', ChargeMembershipFee::DESCRIPTION)
                ->when($chargeTypeId !== null, fn (Builder $query) => $query->orWhere('charge_type_id', $chargeTypeId)))
            ->oldest('id')
            ->first();

        if (BillingMath::cents($settings->membership_fee) <= 0 || $existing?->status === 'Waived') {
            return null;
        }

        if ($existing === null) {
            $properties->first()->charges()->create([
                'code' => $this->codes->next('charge'),
                'charge_type_id' => $chargeTypeId,
                'description' => ChargeMembershipFee::DESCRIPTION,
                'amount' => $settings->membership_fee,
                'due_on' => $dueOn,
                'status' => 'Unpaid',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return 'created';
        }

        $existing->fill(['amount' => $settings->membership_fee, 'due_on' => $dueOn, 'updated_by' => $userId]);

        if (! $existing->isDirty(['amount', 'due_on'])) {
            return null;
        }

        $existing->save();

        return 'updated';
    }

    private function dueOn(Setting $settings, int $year, int $month): string
    {
        $dueMonth = CarbonImmutable::create($year, $month, 1)->addMonthNoOverflow();

        return $dueMonth->day(min($settings->due_day, $dueMonth->daysInMonth))->toDateString();
    }

    private function penaltyRate(Setting $settings): string
    {
        return (string) ($settings->penalty_type === PenaltyType::Fixed ? $settings->penalty_fixed_amount : $settings->penalty_percent);
    }

    /**
     * The promo enrollments of an owner group whose qualifying payment still stands.
     *
     * @param  list<int>  $propertyIds
     * @return EloquentCollection<int, AdvancePaymentEnrollment>
     */
    private function enrollmentsFor(array $propertyIds): EloquentCollection
    {
        return AdvancePaymentEnrollment::query()
            ->with('promo')
            ->whereIn('property_id', $propertyIds)
            ->whereHas('payment', fn (Builder $query) => $query->where('status', '!=', 'Voided'))
            ->get();
    }

    /**
     * @param  EloquentCollection<int, AdvancePaymentEnrollment>  $enrollments
     */
    private function enrollmentCovering(EloquentCollection $enrollments, int $year, int $month): ?AdvancePaymentEnrollment
    {
        return $enrollments->first(fn (AdvancePaymentEnrollment $enrollment): bool => $enrollment->promo->covers($year, $month));
    }
}
