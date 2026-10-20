<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleSticker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleSticker>
 */
class VehicleStickerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('STK-####'),
            'vehicle_id' => Vehicle::factory(),
            'sticker_no' => $this->faker->unique()->numerify('GM-####'),
            'year' => now()->year,
            'issued_on' => now()->toDateString(),
            'expires_on' => now()->endOfYear()->toDateString(),
            'status' => 'Active',
        ];
    }
}
