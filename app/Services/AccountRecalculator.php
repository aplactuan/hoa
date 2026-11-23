<?php

namespace App\Services;

use App\Models\AdvancePaymentEnrollment;
use App\Models\Charge;
use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saves what the account ledger computes as of today: each statement's late penalties and discount credit,
 * every payment's allocation, and the promos the owner has qualified for.
 *
 * Because the ledger replays payments by the date the owner paid, recording a back-dated payment removes
 * penalties it would have prevented, and voiding a payment brings them back.
 */
class AccountRecalculator
{
    public function __construct(private AccountLedger $ledger) {}

    /**
     * @param  Collection<int, Property>  $properties
     * @param  list<int>  $newStatementIds  statements created in this run, so their first penalty is not reported as a change
     */
    public function recalculate(Collection $properties, string $reason, array $newStatementIds = [], bool $noteChanges = true): void
    {
        if ($properties->isEmpty()) {
            return;
        }

        $account = $this->ledger->build($properties, CarbonImmutable::create(9999, 12, 31), now());

        $this->saveStatements($properties, $account['statement_state'], $reason, $noteChanges ? $newStatementIds : null);
        $this->saveAllocations($properties, $account['allocations']);
        $this->saveEnrollments($properties, $account['promos']);
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @param  array<int, array{penalty_cents: int, discount_cents: int, previous_balance_cents: int}>  $state
     * @param  list<int>|null  $newStatementIds  null when changes should not be noted on the statement
     */
    private function saveStatements(Collection $properties, array $state, string $reason, ?array $newStatementIds): void
    {
        foreach (DuesStatement::query()->whereIn('property_id', $properties->modelKeys())->get() as $statement) {
            $computed = $state[$statement->id];
            $oldPenalty = BillingMath::cents($statement->penalty);
            $statement->fill([
                'penalty' => BillingMath::decimal($computed['penalty_cents']),
                'credits' => BillingMath::decimal($computed['discount_cents']),
                'previous_balance' => BillingMath::decimal($computed['previous_balance_cents']),
            ]);

            if ($newStatementIds !== null && $oldPenalty !== $computed['penalty_cents'] && ! in_array($statement->id, $newStatementIds, true)) {
                $note = sprintf('Penalty recalculated from ₱%s to ₱%s on %s: %s.', BillingMath::decimal($oldPenalty), BillingMath::decimal($computed['penalty_cents']), now()->toDateString(), $reason);
                $statement->remarks = trim(($statement->remarks ?? '')."\n".$note);
            }

            if ($statement->isDirty()) {
                $statement->save();
            }
        }
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @param  array<string, array<string, int>>  $allocations  cents keyed by "payment:id", then item key
     */
    private function saveAllocations(Collection $properties, array $allocations): void
    {
        $propertyIds = $properties->modelKeys();
        $itemKeys = DuesStatement::query()->whereIn('property_id', $propertyIds)->pluck('id')->map(fn (int $id): string => (new DuesStatement)->getMorphClass().':'.$id)
            ->merge(Charge::query()->whereIn('property_id', $propertyIds)->where('status', '!=', 'Refunded')->pluck('id')->map(fn (int $id): string => (new Charge)->getMorphClass().':'.$id))
            ->flip();
        $desired = [];

        foreach ($allocations as $source => $items) {
            if (! str_starts_with($source, 'payment:')) {
                continue;
            }

            foreach ($items as $itemKey => $cents) {
                $desired[substr($source, 8).'|'.$itemKey] = $cents;
            }
        }

        $payments = Payment::query()->whereIn('property_id', $propertyIds)->where('status', '!=', 'Voided')->with('allocations')->get();

        foreach ($payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                $itemKey = $allocation->allocatable_type.':'.$allocation->allocatable_id;

                if (! isset($itemKeys[$itemKey])) {
                    continue;
                }

                $key = $payment->id.'|'.$itemKey;
                $cents = $desired[$key] ?? 0;
                unset($desired[$key]);

                if ($cents === 0) {
                    $allocation->delete();
                } elseif (BillingMath::cents($allocation->amount) !== $cents) {
                    $allocation->update(['amount' => BillingMath::decimal($cents)]);
                }
            }
        }

        foreach ($desired as $key => $cents) {
            [$paymentId, $itemKey] = explode('|', $key, 2);
            [$type, $id] = explode(':', $itemKey, 2);

            PaymentAllocation::query()->create([
                'payment_id' => (int) $paymentId,
                'allocatable_type' => $type,
                'allocatable_id' => (int) $id,
                'amount' => BillingMath::decimal($cents),
            ]);
        }
    }

    /**
     * Keeps one enrollment per promo the owner qualifies for, which locks the monthly fee for the covered months.
     *
     * @param  Collection<int, Property>  $properties
     * @param  list<array<string, mixed>>  $promos
     */
    private function saveEnrollments(Collection $properties, array $promos): void
    {
        $propertyIds = $properties->modelKeys();

        foreach ($promos as $promo) {
            $existing = AdvancePaymentEnrollment::query()->where('advance_payment_promo_id', $promo['promo']->id)->whereIn('property_id', $propertyIds)->first();

            if (! $promo['qualified']) {
                $existing?->delete();

                continue;
            }

            $attributes = [
                'property_id' => min($propertyIds),
                'payment_id' => $promo['qualifying_payment_id'],
                'monthly_fee' => BillingMath::decimal($promo['monthly_fee_cents']),
                'discount_total' => BillingMath::decimal($promo['discount_cents']),
            ];

            if ($existing === null) {
                AdvancePaymentEnrollment::query()->create(array_merge($attributes, ['advance_payment_promo_id' => $promo['promo']->id]));
            } else {
                $existing->fill($attributes);

                if ($existing->isDirty()) {
                    $existing->save();
                }
            }
        }
    }
}
