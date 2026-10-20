<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Violation;
use App\Models\ViolationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('VIO-######'),
            'property_id' => Property::factory(),
            'violation_type_id' => ViolationType::factory(),
            'description' => $this->faker->sentence(),
            'fine' => '500.00',
            'status' => 'Open',
        ];
    }
}
