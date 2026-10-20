<?php

namespace Database\Factories;

use App\Models\CaseUpdate;
use App\Models\ServiceCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseUpdate>
 */
class CaseUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_case_id' => ServiceCase::factory(),
            'note' => $this->faker->sentence(),
        ];
    }
}
