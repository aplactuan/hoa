<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\RequestCategory;
use App\Models\ServiceCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCase>
 */
class ServiceCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('REQ-######'),
            'property_id' => Property::factory(),
            'request_category_id' => RequestCategory::factory(),
            'filed_by' => $this->faker->name(),
            'description' => $this->faker->sentence(),
            'priority' => 'Medium',
            'status' => 'Open',
        ];
    }
}
