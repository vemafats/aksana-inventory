<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Str;

class CatalogService
{
    public function generateItemName(Brand $brand, ProductModel $model, Color $color, Size $size): string
    {
        return trim("{$brand->name} {$model->name} {$color->name} {$size->name}");
    }

    public function generateSku(
        Category $category,
        Brand $brand,
        ProductModel $model,
        Color $color,
        Size $size,
    ): string {
        $categoryPart = $this->skuSegment($category->code);
        $brandPart = $this->skuSegment($brand->name);
        $modelPart = $this->skuSegment($model->name);
        $colorPart = $this->skuSegment($color->name);
        $sizePart = $size->name;

        return "{$categoryPart}-{$brandPart}-{$modelPart}-{$colorPart}-{$sizePart}";
    }

    public function generateBarcode(string $sku): string
    {
        $barcode = $sku;
        $counter = 2;

        while (Item::query()->where('barcode', $barcode)->exists()) {
            $barcode = "{$sku}-{$counter}";
            $counter++;
        }

        return $barcode;
    }

    public function createCatalogItem(array $data, User $createdBy): Item
    {
        $category = Category::query()->findOrFail($data['category_id']);
        $brand = Brand::query()->findOrFail($data['brand_id']);
        $model = ProductModel::query()->findOrFail($data['model_id']);
        $color = Color::query()->findOrFail($data['color_id']);
        $size = Size::query()->findOrFail($data['size_id']);

        $itemName = $data['item_name'] ?? $this->generateItemName($brand, $model, $color, $size);
        $sku = $this->makeUniqueSku(
            $this->generateSku($category, $brand, $model, $color, $size)
        );
        $barcode = $this->generateBarcode($sku);

        return Item::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'color_id' => $color->id,
            'size_id' => $size->id,
            'sku' => $sku,
            'barcode' => $barcode,
            'item_name' => $itemName,
            'catalog_photo_path' => $this->normalizePhotoPath($data['catalog_photo_path'] ?? null),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateCatalogItem(Item $item, array $data): Item
    {
        $item->fill([
            'item_name' => $data['item_name'] ?? $item->item_name,
            'catalog_photo_path' => $data['catalog_photo_path'] ?? $item->catalog_photo_path,
            'description' => $data['description'] ?? $item->description,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $item->is_active,
        ]);

        $item->save();

        return $item->refresh();
    }

    private function normalizePhotoPath(mixed $path): ?string
    {
        if (is_array($path)) {
            return $path[0] ?? null;
        }

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function skuSegment(string $value): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '';

        return strtoupper(Str::substr($cleaned, 0, 3));
    }

    private function makeUniqueSku(string $baseSku): string
    {
        $sku = $baseSku;
        $counter = 2;

        while (Item::query()->where('sku', $sku)->exists()) {
            $sku = "{$baseSku}-{$counter}";
            $counter++;
        }

        return $sku;
    }
}
