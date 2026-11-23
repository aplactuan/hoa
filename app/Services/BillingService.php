<?php

namespace App\Services;

use App\Enums\PenaltyType;
use App\Enums\RoleName;
use App\Models\AdvancePaymentEnrollment;
use App\Models\BillingPeriod;
use App\Models\Charge;
use App\Models\DuesStatement;
use App\Models\MonthlyFeeRate;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\StatementAdjustment;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        $dueMonth = CarbonImmutable::create($year, $month, 1)->addMonthNoOverflow();
        $dueOn = $dueMonth->day(min($settings->due_day, $dueMonth->daysInMonth))->toDateString();
        $periodCode = sprintf('%04d%02d', $year, $month);
        $penaltyRate = $settings->penalty_type === PenaltyType::Fixed ? $settings->penalty_fixed_amount : $settings->penalty_percent;
        $count = 0;

        foreach ($this->owners->allGroups() as $propertyIds) {
            DB::transaction(function () use ($propertyIds, $period, $settings, $monthlyFee, $dueOn, $periodCode, $penaltyRate, $year, $month, $userId, &$count): void {
                $properties = Property::query()->whereKey($propertyIds)->orderBy('id')->lockForUpdate()->get();
                $billingPropertyId = $properties->first()?->id;
                $enrollment = $this->enrollmentCovering($propertyIds, $year, $month);
                $created = [];

                foreach ($properties as $property) {
                    if ($property->statements()->where('billing_period_id', $period->id)->exists()) {
                        continue;
                    }

                    $isBillingProperty = $property->id === $billingPropertyId;
                    $regular = $isBillingProperty ? BillingMath::cents($enrollment?->monthly_fee ?? $monthlyFee) : 0;

                    $created[] = $property->statements()->create([
                        'code' => $this->codes->next('statement', $periodCode),
                        'billing_period_id' => $period->id,
                        'regular' => BillingMath::decimal($regular),
                        'special' => '0.00',
                        'penalty' => '0.00',
                        'penalty_type' => $settings->penalty_type,
                        'penalty_rate' => $penaltyRate,
                        'grace_days' => $settings->grace_days,
                        'previous_balance' => '0.00',
                        'credits' => '0.00',
                        'due_on' => $dueOn,
                        'created_by' => $userId,
                    ])->id;
                    $count++;
                }

                if ($created !== []) {
                    $this->recalculator->recalculate($properties, 'statement generation', $created);
                }
            });
        }

        return $count;
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
     * @param  list<int>  $propertyIds
     */
    private function enrollmentCovering(array $propertyIds, int $year, int $month): ?AdvancePaymentEnrollment
    {
        return AdvancePaymentEnrollment::query()
            ->with('promo')
            ->whereIn('property_id', $propertyIds)
            ->whereHas('payment', fn (Builder $query) => $query->where('status', '!=', 'Voided'))
            ->get()
            ->first(fn (AdvancePaymentEnrollment $enrollment): bool => $enrollment->promo->covers($year, $month));
    }
}
