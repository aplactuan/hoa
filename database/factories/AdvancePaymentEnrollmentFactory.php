<?php

namespace Database\Factories;

use App\Models\AdvancePaymentEnrollment;
use App\Models\AdvancePaymentPromo;
use App\Models\Payment;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancePaymentEnrollment>
 */
class AdvancePaymentEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advance_payment_promo_id' => AdvancePaymentPromo::factory(),
            'property_id' => Property::factory(),
            'payment_id' => Payment::factory(),
            'monthly_fee' => '1000.00',
            'discount_total' => '1200.00',
        ];
    }
}
