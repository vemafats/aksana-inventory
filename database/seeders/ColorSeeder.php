<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Hitam', 'code' => '#000000'],
            ['name' => 'Putih', 'code' => '#FFFFFF'],
            ['name' => 'Merah', 'code' => '#FF0000'],
            ['name' => 'Biru', 'code' => '#0000FF'],
            ['name' => 'Abu-abu', 'code' => '#808080'],
            ['name' => 'Coklat', 'code' => '#8B4513'],
            ['name' => 'Navy', 'code' => '#000080'],
            ['name' => 'Hijau', 'code' => '#008000'],
        ];

        foreach ($colors as $color) {
            Color::firstOrCreate(
                ['name' => $color['name']],
                [
                    'id' => (string) Str::uuid(),
                    'code' => $color['code'],
                    'is_active' => true,
                ]
            );
        }
    }
}
