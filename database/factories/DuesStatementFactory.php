<?php

namespace Database\Factories;

use App\Models\BillingPeriod;
use App\Models\DuesStatement;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuesStatement>
 */
class DuesStatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('SOA-########-###'),
            'property_id' => Property::factory(),
            'billing_period_id' => BillingPeriod::factory(),
            'regular' => '1000.00',
            'special' => '0.00',
            'penalty' => '0.00',
            'previous_balance' => '0.00',
            'credits' => '0.00',
            'due_on' => now()->addMonth()->day(15)->toDateString(),
        ];
    }
}
