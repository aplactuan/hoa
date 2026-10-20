<?php

namespace Database\Factories;

use App\Models\BillingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPeriod>
 */
class BillingPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => $this->faker->unique()->numberBetween(2020, 2100),
            'month' => 1,
            'generated_at' => now(),
        ];
    }
}
