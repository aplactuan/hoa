<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Street;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Street>
 */
class StreetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'name' => fake()->unique()->streetName(),
            'is_active' => true,
        ];
    }
}
