<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\AdvancePaymentPromo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancePaymentPromo>
 */
class AdvancePaymentPromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Annual prepayment',
            'discount_type' => DiscountType::Percent,
            'discount_value' => '10.00',
            'pay_by' => now()->addMonth()->toDateString(),
            'covers_from' => now()->addMonth()->startOfMonth()->toDateString(),
            'covers_to' => now()->addMonths(12)->startOfMonth()->toDateString(),
            'is_active' => true,
        ];
    }
}
