<?php

namespace Database\Factories;

use App\Models\DuesStatement;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'allocatable_type' => DuesStatement::class,
            'allocatable_id' => DuesStatement::factory(),
            'amount' => '50.00',
        ];
    }
}
