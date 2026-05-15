<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'SEP', 'name' => 'Sepatu'],
            ['code' => 'TAS', 'name' => 'Tas'],
            ['code' => 'KAO', 'name' => 'Kaos'],
            ['code' => 'TOP', 'name' => 'Topi'],
            ['code' => 'CEL', 'name' => 'Celana'],
            ['code' => 'JAK', 'name' => 'Jaket'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['code' => $category['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
