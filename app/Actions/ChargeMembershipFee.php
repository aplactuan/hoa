<?php

namespace App\Actions;

use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\Owner;
use App\Models\Setting;
use App\Services\BillingService;
use App\Support\BillingMath;
use Illuminate\Database\Eloquent\Builder;

/**
 * Charges the one-time membership fee when a new member joins the association.
 *
 * An owner who already has another owner record (matched by portal account or email) is a returning
 * member and is not charged again. A co-owner added beside existing current owners is not charged either.
 */
class ChargeMembershipFee
{
    public const DESCRIPTION = 'Membership fee';

    public function __construct(private BillingService $billing) {}

    public function handle(Owner $owner, ?int $actorId = null): ?Charge
    {
        $fee = Setting::current()->membership_fee;

        if (BillingMath::cents($fee) <= 0 || $this->isJoiningExistingOwners($owner) || $this->isReturningMember($owner)) {
            return null;
        }

        return $this->billing->createCharge(
            $owner->property,
            self::DESCRIPTION,
            $fee,
            now()->toDateString(),
            $actorId,
            ChargeType::query()->where('name', 'Membership Fee')->value('id'),
        );
    }

    private function isJoiningExistingOwners(Owner $owner): bool
    {
        return Owner::query()
            ->where('property_id', $owner->property_id)
            ->where('status', 'Current')
            ->whereKeyNot($owner->id)
            ->exists();
    }

    private function isReturningMember(Owner $owner): bool
    {
        if ($owner->user_id === null && blank($owner->email)) {
            return false;
        }

        return Owner::withTrashed()
            ->whereKeyNot($owner->id)
            ->where(function (Builder $query) use ($owner): void {
                if ($owner->user_id !== null) {
                    $query->orWhere('user_id', $owner->user_id);
                }

                if (filled($owner->email)) {
                    $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower($owner->email)]);
                }
            })
            ->exists();
    }
}
