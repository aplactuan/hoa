<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Property;
use App\Models\RequestCategory;
use App\Services\BillingService;
use App\Services\DisplayCode;
use App\Services\OfficeService;
use App\Services\PropertyRegistry;
use Illuminate\Database\Seeder;

class HoaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || Property::query()->exists()) {
            return;
        }

        $this->call(MasterDataSeeder::class);
        $registry = app(PropertyRegistry::class);
        $billing = app(BillingService::class);
        $office = app(OfficeService::class);
        $codes = app(DisplayCode::class);

        for ($lot = 1; $lot <= 10; $lot++) {
            $property = $registry->createProperty(['block' => 1, 'lot' => $lot, 'lot_area' => (string) (100 + $lot * 5)]);
            $owner = $registry->transferOwnership($property, [
                'name' => fake()->name(),
                'ownership_type' => 'Sole Owner',
                'started_on' => now()->subYear()->toDateString(),
            ]);
            $resident = $registry->moveIn($property, [
                'name' => $owner->name,
                'occupancy_type' => 'Owner-Occupied',
                'moved_in_on' => now()->subMonths(6)->toDateString(),
            ]);
            $property->familyMembers()->create([
                'code' => $codes->next('family_member'),
                'resident_id' => $resident->id,
                'name' => fake()->name(),
                'relationship' => 'Child',
                'status' => 'Active',
            ]);
            $vehicle = $registry->registerVehicle($property, [
                'driver_name' => $owner->name,
                'type' => 'Car',
                'plate_no' => 'GM '.str_pad((string) $lot, 4, '0', STR_PAD_LEFT),
            ]);
            $registry->issueSticker($vehicle, 'GM-'.now()->year.'-'.str_pad((string) $lot, 3, '0', STR_PAD_LEFT), now()->year);

            if ($lot === 1) {
                $office->logCase($property, RequestCategory::query()->first(), $owner->name, 'Streetlight near the property needs repair.', 'High');
                $office->bookFacility($property, Facility::query()->firstOrFail(), now()->addWeek()->toDateString(), '10:00', '12:00', 'Family gathering', 20);
            }
        }

        $billing->generatePeriod(now()->year, now()->month);

        foreach (Property::query()->orderBy('id')->limit(5)->get() as $property) {
            $billing->recordPayment($property, ['amount' => '600.00', 'method' => 'Cash', 'paid_on' => now()->toDateString()]);
        }
    }
}
