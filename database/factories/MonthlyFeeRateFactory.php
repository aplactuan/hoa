<?php

namespace Database\Factories;

use App\Models\MonthlyFeeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyFeeRate>
 */
class MonthlyFeeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => '1000.00',
            'effective_from' => now()->startOfMonth()->toDateString(),
        ];
    }
}
