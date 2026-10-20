<?php

namespace Database\Seeders;

use App\Enums\DeviceKind;
use App\Models\ChargeType;
use App\Models\Device;
use App\Models\Facility;
use App\Models\PropertyType;
use App\Models\RequestCategory;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Street;
use App\Models\TransactionType;
use App\Models\ViolationType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed association settings, devices, and lookup lists.
     *
     * Fees and fines are placeholders until the board confirms the schedule.
     */
    public function run(): void
    {
        $setting = Setting::query()->first();

        if ($setting === null) {
            Setting::query()->create([
                'dues_rate_per_sqm' => 10,
                'penalty_percent' => 2,
                'due_day' => 15,
                'grace_days' => 0,
                'expiry_warning_days' => 45,
                'hoa_name' => 'Green Meadows Homeowners Association',
                'hoa_address' => 'Green Meadows, Philippines',
            ]);
        }

        $phaseOne = Section::query()->updateOrCreate(['name' => 'Phase 1'], ['is_active' => true]);

        Street::query()->updateOrCreate(
            ['name' => 'Meadow Lane', 'section_id' => $phaseOne->id],
            ['is_active' => true],
        );
        Street::query()->updateOrCreate(
            ['name' => 'Creek Road', 'section_id' => $phaseOne->id],
            ['is_active' => true],
        );

        foreach (['Lot', 'House', 'Townhouse'] as $propertyType) {
            PropertyType::query()->updateOrCreate(['name' => $propertyType], ['is_active' => true]);
        }

        TransactionType::query()->updateOrCreate(
            ['name' => 'Car Sticker'],
            ['default_fee' => 600, 'is_active' => true],
        );
        TransactionType::query()->updateOrCreate(
            ['name' => 'ID Replacement'],
            ['default_fee' => 100, 'is_active' => true],
        );

        foreach (['Monthly Dues', 'Penalty', 'Special Assessment'] as $chargeType) {
            ChargeType::query()->updateOrCreate(['name' => $chargeType], ['is_active' => true]);
        }

        ViolationType::query()->updateOrCreate(
            ['name' => 'Illegal Parking'],
            ['default_fine' => 500, 'is_active' => true],
        );
        ViolationType::query()->updateOrCreate(
            ['name' => 'Noise'],
            ['default_fine' => 300, 'is_active' => true],
        );

        foreach (['Maintenance', 'Document Request'] as $category) {
            RequestCategory::query()->updateOrCreate(['name' => $category], ['is_active' => true]);
        }

        Facility::query()->updateOrCreate(
            ['name' => 'Clubhouse'],
            ['fee' => 1500, 'deposit' => 500, 'is_active' => true],
        );
        Facility::query()->updateOrCreate(
            ['name' => 'Basketball Court'],
            ['fee' => 300, 'deposit' => 200, 'is_active' => true],
        );

        Device::query()->updateOrCreate(
            ['name' => 'Front Desk PC'],
            ['kind' => DeviceKind::Pc, 'location' => 'Administration office', 'is_active' => true],
        );
        Device::query()->updateOrCreate(
            ['name' => 'Finance PC'],
            ['kind' => DeviceKind::Pc, 'location' => 'Finance office', 'is_active' => true],
        );
        Device::query()->updateOrCreate(
            ['name' => 'Guardhouse Tablet'],
            ['kind' => DeviceKind::Tablet, 'location' => 'Guardhouse', 'is_active' => true],
        );
    }
}
