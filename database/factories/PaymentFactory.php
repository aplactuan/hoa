<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('PAY-######'),
            'property_id' => Property::factory(),
            'or_no' => $this->faker->unique()->numerify('OR-######'),
            'paid_on' => now()->toDateString(),
            'amount' => '100.00',
            'method' => 'Cash',
            'status' => 'Posted',
        ];
    }
}
