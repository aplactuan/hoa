<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('PRP-####'),
            'account_no' => $this->faker->unique()->numerify('ACC-####'),
            'membership_no' => $this->faker->unique()->numerify('MEM-####'),
            'block' => $this->faker->numberBetween(1, 1000),
            'lot' => $this->faker->unique()->numberBetween(1, 100000),
            'lot_area' => $this->faker->randomFloat(2, 80, 500),
            'status' => 'Vacant',
        ];
    }
}
