<?php

namespace Database\Factories;

use App\Models\Owner;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Owner>
 */
class OwnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('OWN-####'),
            'property_id' => Property::factory(),
            'name' => $this->faker->name(),
            'ownership_type' => 'Sole Owner',
            'started_on' => now()->toDateString(),
            'status' => 'Current',
        ];
    }
}
