<?php

namespace Database\Factories;

use App\Enums\DeviceKind;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'kind' => DeviceKind::Pc,
            'location' => fake()->city(),
            'is_active' => true,
        ];
    }
}
