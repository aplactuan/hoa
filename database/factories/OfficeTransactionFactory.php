<?php

namespace Database\Factories;

use App\Models\OfficeTransaction;
use App\Models\Property;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeTransaction>
 */
class OfficeTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('TRX-######'),
            'property_id' => Property::factory(),
            'transaction_type_id' => TransactionType::factory(),
            'requested_by' => $this->faker->name(),
            'amount' => '0.00',
            'status' => 'Pending',
        ];
    }
}
