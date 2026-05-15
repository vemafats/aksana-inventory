<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        $locations = [
            [
                'location_code' => 'GUD-001',
                'location_name' => 'Gudang Pusat Aksana',
                'location_type' => 'central_warehouse',
                'status' => 'active',
                'start_date' => null,
                'end_date' => null,
            ],
            [
                'location_code' => 'BAZ-001',
                'location_name' => 'Bazar Grand Indonesia',
                'location_type' => 'bazar',
                'status' => 'active',
                'start_date' => $today->toDateString(),
                'end_date' => $today->copy()->addDays(7)->toDateString(),
            ],
            [
                'location_code' => 'BAZ-002',
                'location_name' => 'Bazar Summarecon Mal Serpong',
                'location_type' => 'bazar',
                'status' => 'draft',
                'start_date' => null,
                'end_date' => null,
            ],
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(
                ['location_code' => $location['location_code']],
                [
                    'id' => (string) Str::uuid(),
                    'location_name' => $location['location_name'],
                    'location_type' => $location['location_type'],
                    'address' => null,
                    'start_date' => $location['start_date'],
                    'end_date' => $location['end_date'],
                    'status' => $location['status'],
                ]
            );
        }
    }
}
