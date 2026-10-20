<?php

namespace Database\Factories;

use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('FAM-####'),
            'property_id' => Property::factory(),
            'resident_id' => Resident::factory(),
            'name' => $this->faker->name(),
            'relationship' => 'Relative',
            'status' => 'Active',
        ];
    }
}
