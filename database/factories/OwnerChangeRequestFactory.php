<?php

namespace Database\Factories;

use App\Enums\OwnerChangeStatus;
use App\Enums\OwnerChangeType;
use App\Models\OwnerChangeRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerChangeRequest>
 */
class OwnerChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'requested_by' => User::factory(),
            'type' => OwnerChangeType::OwnerContact,
            'payload' => ['phone' => fake()->phoneNumber()],
            'status' => OwnerChangeStatus::Pending,
        ];
    }
}
