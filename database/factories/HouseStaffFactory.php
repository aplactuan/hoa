<?php

namespace Database\Factories;

use App\Models\HouseStaff;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseStaff>
 */
class HouseStaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('STA-####'),
            'property_id' => Property::factory(),
            'name' => $this->faker->name(),
            'role' => 'Helper',
            'started_on' => now()->toDateString(),
            'id_type' => 'Government ID',
            'id_reference' => $this->faker->bothify('ID-########'),
            'pass_no' => $this->faker->unique()->numerify('PASS-####'),
            'pass_expires_on' => now()->endOfYear()->toDateString(),
            'status' => 'Active',
        ];
    }
}
