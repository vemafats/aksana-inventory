<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductModelSeeder extends Seeder
{
    public function run(): void
    {
        $combinations = [
            'Nike' => [
                'category' => 'SEP',
                'models' => ['Air Max', 'Air Force 1', 'Revolution'],
            ],
            'Adidas' => [
                'category' => 'SEP',
                'models' => ['Samba', 'Stan Smith', 'Ultraboost'],
            ],
            'Ventela' => [
                'category' => 'SEP',
                'models' => ['Original', 'Street', 'High'],
            ],
            'Compass' => [
                'category' => 'SEP',
                'models' => ['Nagata', 'Ponti', 'Dablo'],
            ],
            'Zara' => [
                'category' => 'KAO',
                'models' => ['Basic Tee', 'Stripe Tee', 'Oversized'],
            ],
            'H&M' => [
                'category' => 'KAO',
                'models' => ['Essential', 'Relaxed', 'Fitted'],
            ],
        ];

        foreach ($combinations as $brandName => $data) {
            $category = Category::where('code', $data['category'])->first();
            $brand = Brand::where('name', $brandName)->first();

            if (! $category || ! $brand) {
                continue;
            }

            foreach ($data['models'] as $modelName) {
                ProductModel::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'name' => $modelName,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
