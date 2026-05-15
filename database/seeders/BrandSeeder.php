<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Nike',
            'Adidas',
            'Zara',
            'H&M',
            'Compass',
            'Ventela',
        ];

        foreach ($brands as $name) {
            Brand::firstOrCreate(
                ['name' => $name],
                [
                    'id' => (string) Str::uuid(),
                    'is_active' => true,
                ]
            );
        }
    }
}
