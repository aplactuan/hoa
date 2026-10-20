<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\FacilityReservation;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityReservation>
 */
class FacilityReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('RES-######'),
            'property_id' => Property::factory(),
            'facility_id' => Facility::factory(),
            'event_on' => now()->addWeek()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'purpose' => 'Community gathering',
            'guests' => 20,
            'fee' => '0.00',
            'deposit' => '0.00',
            'approval_status' => 'Pending',
            'payment_status' => 'Unpaid',
        ];
    }
}
