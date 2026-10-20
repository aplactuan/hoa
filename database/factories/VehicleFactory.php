<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('VEH-####'),
            'property_id' => Property::factory(),
            'driver_name' => $this->faker->name(),
            'type' => 'Car',
            'plate_no' => $plate = strtoupper($this->faker->unique()->bothify('??? ####')),
            'normalized_plate' => str_replace(' ', '', $plate),
            'status' => 'Active',
        ];
    }
}
