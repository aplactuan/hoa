<?php

namespace Database\Factories;

use App\Models\ChargeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeType>
 */
class ChargeTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
