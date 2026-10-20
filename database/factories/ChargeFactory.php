<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('CHG-######'),
            'property_id' => Property::factory(),
            'description' => 'Permit fee',
            'amount' => '100.00',
            'due_on' => now()->addDays(15)->toDateString(),
            'status' => 'Unpaid',
        ];
    }
}
