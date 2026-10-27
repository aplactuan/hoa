<?php

namespace App\Services;

use App\Models\FamilyMember;
use App\Models\HouseStaff;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Vehicle;

class GateCheckService
{
    /** @return list<array{kind: string, verdict: string, name: string, details: string, property: string}> */
    public function check(string $query): array
    {
        $term = trim(preg_replace('/[^\pL\pN -]/u', '', $query) ?? '');

        if (mb_strlen($term) < 2) {
            return [];
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $term));
        $results = [];

        $vehicles = Vehicle::query()->with(['property.street', 'stickers'])
            ->where(function ($builder) use ($normalized, $term): void {
                $builder->where('normalized_plate', 'like', '%'.$normalized.'%')
                    ->orWhereHas('stickers', fn ($stickers) => $stickers->where('sticker_no', 'like', '%'.$term.'%'));
            })->limit(8)->get();

        foreach ($vehicles as $vehicle) {
            $sticker = $vehicle->stickers->sortByDesc('year')->first();
            $valid = $vehicle->status === 'Active'
                && $sticker?->status === 'Active'
                && $sticker->expires_on->toDateString() >= now()->toDateString();
            $results[] = [
                'kind' => 'Vehicle',
                'verdict' => $valid ? 'VALID' : 'EXPIRED',
                'name' => $vehicle->plate_no,
                'details' => trim(implode(' · ', array_filter([$vehicle->color, $vehicle->make, $vehicle->model, $sticker?->sticker_no]))),
                'property' => $this->location($vehicle->property),
            ];
        }

        foreach (HouseStaff::query()->with('property.street')
            ->where('pass_no', 'like', '%'.$term.'%')
            ->orWhere('name', 'like', '%'.$term.'%')
            ->limit(8)->get() as $staff) {
            $valid = $staff->status === 'Active' && $staff->pass_expires_on->toDateString() >= now()->toDateString();
            $results[] = [
                'kind' => 'House staff',
                'verdict' => $valid ? 'PASS OK' : 'PASS EXP.',
                'name' => $staff->name,
                'details' => $staff->role.' · '.$staff->pass_no,
                'property' => $this->location($staff->property),
            ];
        }

        foreach (Resident::query()->with('property.street')->where('name', 'like', '%'.$term.'%')->limit(8)->get() as $resident) {
            $results[] = [
                'kind' => 'Resident',
                'verdict' => $resident->status === 'Active' ? 'RESIDENT' : 'MOVED OUT',
                'name' => $resident->name,
                'details' => $resident->occupancy_type,
                'property' => $this->location($resident->property),
            ];
        }

        foreach (FamilyMember::query()->with('property.street')->where('name', 'like', '%'.$term.'%')->limit(8)->get() as $member) {
            $results[] = [
                'kind' => 'Family',
                'verdict' => $member->status === 'Active' ? 'RESIDENT' : 'MOVED OUT',
                'name' => $member->name,
                'details' => $member->relationship,
                'property' => $this->location($member->property),
            ];
        }

        return array_slice($results, 0, 8);
    }

    private function location(Property $property): string
    {
        return 'Block '.$property->block.' · Lot '.$property->lot.' · '.($property->street->name ?? '');
    }
}
