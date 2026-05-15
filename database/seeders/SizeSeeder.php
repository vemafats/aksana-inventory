<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $clothingSizes = ['S', 'M', 'L', 'XL', 'XXL'];

        foreach ($clothingSizes as $index => $name) {
            Size::firstOrCreate(
                ['name' => $name, 'size_type' => 'clothing'],
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
                ['name' => $name, 'size_type' => 'shoes'],
                [
                    'id' => (string) Str::uuid(),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
