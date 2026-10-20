<?php

namespace Database\Factories;

use App\Models\Correspondence;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Correspondence>
 */
class CorrespondenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('COR-######'),
            'property_id' => Property::factory(),
            'type' => 'Letter',
            'subject' => $this->faker->sentence(),
            'sender' => 'Green Meadows HOA',
            'recipient' => $this->faker->name(),
            'status' => 'Pending',
        ];
    }
}
