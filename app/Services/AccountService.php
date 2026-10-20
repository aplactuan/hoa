<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\Property;
use App\Support\BillingMath;

class AccountService
{
    public function statementBalanceCents(DuesStatement $statement): int
    {
        $base = BillingMath::cents($statement->regular)
            + BillingMath::cents($statement->special)
            + BillingMath::cents($statement->penalty)
            - BillingMath::cents($statement->credits);
        $adjustments = ($statement->relationLoaded('adjustments') ? $statement->adjustments : $statement->adjustments()->get())
            ->where('status', 'Approved')
            ->sum(fn ($adjustment): int => BillingMath::cents($adjustment->amount));
        $paid = ($statement->relationLoaded('allocations') ? $statement->allocations : $statement->allocations()->with('payment')->get())
            ->filter(fn ($allocation): bool => $allocation->payment !== null && $allocation->payment->status !== 'Voided')
            ->sum(fn ($allocation): int => BillingMath::cents($allocation->amount));

        return max(0, $base - $adjustments - $paid);
    }

    public function chargeBalanceCents(Charge $charge): int
    {
        if (in_array($charge->status, ['Waived', 'Refunded'], true)) {
            return 0;
        }

        $paid = ($charge->relationLoaded('allocations') ? $charge->allocations : $charge->allocations()->with('payment')->get())
            ->filter(fn ($allocation): bool => $allocation->payment !== null && $allocation->payment->status !== 'Voided')
            ->sum(fn ($allocation): int => BillingMath::cents($allocation->amount));

        return max(0, BillingMath::cents($charge->amount) - $paid);
    }

    public function unappliedCents(Payment $payment): int
    {
        if ($payment->status === 'Voided') {
            return 0;
        }

        $allocated = ($payment->relationLoaded('allocations') ? $payment->allocations : $payment->allocations()->get())
            ->sum(fn ($allocation): int => BillingMath::cents($allocation->amount));

        return max(0, BillingMath::cents($payment->amount) - $allocated);
    }

    /** @return array{outstanding_cents: int, credit_cents: int, overdue_months: int, standing: string} */
    public function summary(Property $property, bool $useLoaded = false): array
    {
        if ($useLoaded) {
            $property->loadMissing(['statements.allocations.payment', 'statements.adjustments', 'charges.allocations.payment', 'payments.allocations']);
            $statements = $property->statements;
            $charges = $property->charges;
            $payments = $property->payments->where('status', '!=', 'Voided');
        } else {
            $statements = $property->statements()->with(['allocations.payment', 'adjustments'])->get();
            $charges = $property->charges()->with('allocations.payment')->get();
            $payments = $property->payments()->with('allocations')->where('status', '!=', 'Voided')->get();
        }

        $receivable = $statements->sum(fn (DuesStatement $statement): int => $this->statementBalanceCents($statement))
            + $charges->sum(fn (Charge $charge): int => $this->chargeBalanceCents($charge));
        $credit = $payments->sum(fn (Payment $payment): int => $this->unappliedCents($payment));
        $net = $receivable - $credit;
        $overdueMonths = $statements
            ->filter(fn (DuesStatement $statement): bool => $statement->due_on->isPast() && $this->statementBalanceCents($statement) > 0)
            ->count();

        return [
            'outstanding_cents' => max(0, $net),
            'credit_cents' => max(0, -$net),
            'overdue_months' => $overdueMonths,
            'standing' => $overdueMonths >= 3 ? 'Delinquent' : ($overdueMonths > 0 ? 'With Arrears' : 'Good Standing'),
        ];
    }
}
