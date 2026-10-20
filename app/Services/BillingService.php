<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\BillingPeriod;
use App\Models\Charge;
use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\StatementAdjustment;
use App\Support\BillingMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BillingService
{
    public function __construct(private DisplayCode $codes, private AccountService $accounts, private OfficeAlerts $alerts) {}

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
        $count = 0;
        $periodCode = sprintf('%04d%02d', $year, $month);

        Property::query()->orderBy('id')->chunkById(100, function ($properties) use ($period, $settings, $periodCode, $year, $month, $userId, &$count): void {
            foreach ($properties as $property) {
                DB::transaction(function () use ($period, $settings, $periodCode, $year, $month, $userId, $property, &$count): void {
                    if ($property->statements()->where('billing_period_id', $period->id)->exists()) {
                        return;
                    }

                    $previousBalance = $this->accounts->summary($property)['outstanding_cents'];
                    $regular = BillingMath::regularDues($property->lot_area, $settings->dues_rate_per_sqm);
                    $penalty = BillingMath::penalty($previousBalance, $settings->penalty_percent);
                    $dueDay = min($settings->due_day, cal_days_in_month(CAL_GREGORIAN, $month, $year));

                    $statement = $property->statements()->create([
                        'code' => $this->codes->next('statement', $periodCode),
                        'billing_period_id' => $period->id,
                        'regular' => BillingMath::decimal($regular),
                        'special' => '0.00',
                        'penalty' => BillingMath::decimal($penalty),
                        'previous_balance' => BillingMath::decimal($previousBalance),
                        'credits' => '0.00',
                        'due_on' => sprintf('%04d-%02d-%02d', $year, $month, $dueDay),
                        'created_by' => $userId,
                    ]);
                    $this->applyAdvanceCredits($property, $statement);
                    $count++;
                });
            }
        });

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

            $this->allocate($payment, $amount);

            return $payment;
        });
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

        $payment->update(['status' => 'Voided', 'voided_by' => $administratorId]);
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

        $adjustment->update(['status' => 'Approved', 'approved_by' => $administratorId, 'approved_at' => now()]);
    }

    public function createCharge(Property $property, string $description, string $amount, ?string $dueOn = null, ?int $userId = null): Charge
    {
        if (BillingMath::cents($amount) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Charge amount must be positive.']);
        }

        return DB::transaction(fn (): Charge => $property->charges()->create([
            'code' => $this->codes->next('charge'),
            'description' => $description,
            'amount' => $amount,
            'due_on' => $dueOn,
            'status' => 'Unpaid',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]));
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

        $charge->update(['status' => 'Waived', 'waived_by' => $administratorId]);
    }

    private function allocate(Payment $payment, int $remaining): void
    {
        $property = $payment->property;
        $items = $property->statements()->orderBy('due_on')->get()
            ->concat($property->charges()->whereNotIn('status', ['Waived', 'Refunded'])->orderBy('due_on')->get())
            ->sortBy(fn ($item) => $item->due_on?->toDateString() ?? '9999-12-31');

        foreach ($items as $item) {
            $due = $item instanceof DuesStatement
                ? $this->accounts->statementBalanceCents($item)
                : $this->accounts->chargeBalanceCents($item);
            $applied = min($remaining, $due);

            if ($applied > 0) {
                $item->allocations()->create(['payment_id' => $payment->id, 'amount' => BillingMath::decimal($applied)]);
                $remaining -= $applied;
            }

            if ($remaining === 0) {
                break;
            }
        }
    }

    private function applyAdvanceCredits(Property $property, DuesStatement $statement): void
    {
        $remaining = $this->accounts->statementBalanceCents($statement);

        foreach ($property->payments()->where('status', '!=', 'Voided')->oldest('paid_on')->get() as $payment) {
            $available = $this->accounts->unappliedCents($payment);
            $applied = min($available, $remaining);

            if ($applied > 0) {
                $statement->allocations()->create(['payment_id' => $payment->id, 'amount' => BillingMath::decimal($applied)]);
                $remaining -= $applied;
            }

            if ($remaining === 0) {
                break;
            }
        }
    }
}
