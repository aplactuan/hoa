<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('RES-####'),
            'property_id' => Property::factory(),
            'name' => $this->faker->name(),
            'occupancy_type' => 'Owner-Occupied',
            'moved_in_on' => now()->toDateString(),
            'status' => 'Active',
        ];
    }
}
