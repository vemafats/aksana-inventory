<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $kaosCategory = Category::query()->where('code', 'KAO')->first();
        $sepatuCategory = Category::query()->where('code', 'SEP')->first();

        $clothingSizes = ['S', 'M', 'L', 'XL', 'XXL'];

        foreach ($clothingSizes as $index => $name) {
            Size::firstOrCreate(
                ['name' => $name, 'category_id' => $kaosCategory?->id],
                [
                    'id' => (string) Str::uuid(),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $shoeSizes = ['37', '38', '39', '40', '41', '42', '43'];

        foreach ($shoeSizes as $index => $name) {
            Size::firstOrCreate(
                ['name' => $name, 'category_id' => $sepatuCategory?->id],
                [
                    'id' => (string) Str::uuid(),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
