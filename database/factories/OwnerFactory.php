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
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();

        return [
            'code' => $this->faker->unique()->numerify('OWN-####'),
            'property_id' => Property::factory(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => Owner::displayName($firstName, $lastName),
            'ownership_type' => 'Sole Owner',
            'started_on' => now()->toDateString(),
            'status' => 'Current',
        ];
    }
}
