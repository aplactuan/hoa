<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'fee' => fake()->randomFloat(2, 0, 2000),
            'deposit' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
        ];
    }
}
