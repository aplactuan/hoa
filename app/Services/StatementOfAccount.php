<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PenaltyType;
use App\Models\AdvancePaymentPromo;
use App\Models\MonthlyFeeRate;
use App\Models\Owner;
use App\Models\PenaltyAmnesty;
use App\Models\Property;
use App\Models\Setting;
use App\Support\BillingMath;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * A homeowner's statement of account: the account ledger plus who it is for and the billing rules behind it.
 */
class StatementOfAccount
{
    public function __construct(private AccountLedger $ledger) {}

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<string, mixed>
     */
    public function forProperties(Collection $properties, ?CarbonInterface $asOf = null): array
    {
        $properties = $properties->sortBy('id')->values();
        $properties->loadMissing('currentOwner');
        $billingProperty = $properties->first();
        $memberSince = Owner::query()->whereIn('property_id', $properties->modelKeys())->where('status', 'Current')->min('started_on');

        return array_merge($this->ledger->build($properties, $asOf), [
            'owner_name' => $billingProperty?->currentOwner?->name ?? 'Property owner',
            'properties' => $properties->map(fn (Property $property): array => [
                'code' => $property->code,
                'block_lot' => 'Block '.$property->block.' Lot '.$property->lot,
                'address' => $property->property_address,
            ])->all(),
            'multiple_properties' => $properties->count() > 1,
            'member_since' => $memberSince === null ? null : CarbonImmutable::parse($memberSince)->toDateString(),
            'rules' => $this->rules(),
        ]);
    }

    public static function peso(int $cents): string
    {
        return ($cents < 0 ? '−' : '').'₱'.number_format(abs($cents) / 100, 2);
    }

    public static function amount(int $cents): string
    {
        return $cents === 0 ? '—' : number_format(abs($cents) / 100, 2);
    }

    /**
     * @return list<string>
     */
    private function rules(): array
    {
        $settings = Setting::current();
        $monthlyDues = MonthlyFeeRate::amountFor(now()->year, now()->month);
        $rules = [];

        if (BillingMath::cents($settings->membership_fee) > 0) {
            $rules[] = 'Membership fee '.self::peso(BillingMath::cents($settings->membership_fee)).' one time, no penalty';
        }

        $rules[] = 'Monthly dues '.self::peso(BillingMath::cents($monthlyDues));
        $rules[] = 'Due on the '.$this->ordinal($settings->due_day).' of the following month';
        $rules[] = 'Payments go to dues first, penalties last';

        $penalty = $settings->penalty_type === PenaltyType::Fixed
            ? self::peso(BillingMath::cents($settings->penalty_fixed_amount))
            : rtrim(rtrim($settings->penalty_percent, '0'), '.').'% ('.self::peso(BillingMath::percentOf(BillingMath::cents($monthlyDues), $settings->penalty_percent)).')';
        $rules[] = 'Penalty '.$penalty.' on each unpaid month, every month after the due date'.($settings->grace_days > 0 ? ' plus '.$settings->grace_days.' grace days' : '');

        foreach (AdvancePaymentPromo::query()->where('is_active', true)->whereDate('pay_by', '>=', now()->subYear())->orderBy('pay_by')->get() as $promo) {
            $discount = $promo->discount_type === DiscountType::Percent ? rtrim(rtrim($promo->discount_value, '0'), '.').'% off' : self::peso(BillingMath::cents($promo->discount_value)).' off';
            $rules[] = $discount.' '.$promo->covers_from->format('M Y').'–'.$promo->covers_to->format('M Y').' if prepaid by '.$promo->pay_by->format('M j, Y');
        }

        foreach (PenaltyAmnesty::query()->where('is_active', true)->whereDate('ends_on', '>=', now()->subYear())->orderBy('ends_on')->get() as $amnesty) {
            $rules[] = 'Penalties waived on dues paid by '.$amnesty->ends_on->format('M j, Y');
        }

        return $rules;
    }

    private function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }
}
