<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogService;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ColorSeeder::class,
            SizeSeeder::class,
            ProductModelSeeder::class,
            LocationSeeder::class,
        ]);
    }

    public function test_dashboard_summary_returns_correct_structure(): void
    {
        $response = $this->actingAsOwner()
            ->getJson('/api/reports/dashboard-summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_sku',
                    'total_unit_stock',
                    'active_locations',
                    'low_stock_count',
                    'todays_sales',
                    'todays_transactions',
                ],
            ]);
    }

    public function test_warehouse_stock_returns_items(): void
    {
        $item = $this->createCatalogItem();
        $this->stockIn($item, 5);

        $response = $this->actingAsOwner()
            ->getJson('/api/reports/warehouse-stock');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $skus = collect($response->json('data.data'))->pluck('sku');
        $this->assertTrue($skus->contains($item->sku));

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost', $content);
        $this->assertStringNotContainsString('latest_supplier_cost', $content);
    }

    public function test_low_stock_items_threshold_is_one(): void
    {
        $lowItem = $this->createCatalogItem();
        $normalItem = $this->createCatalogItemWithSize('41');

        $this->stockIn($lowItem, 1);
        $this->stockIn($normalItem, 2);

        $response = $this->actingAsOwner()
            ->getJson('/api/reports/low-stock-items');

        $response->assertOk();

        $itemIds = collect($response->json('data'))->pluck('item_id');

        $this->assertTrue($itemIds->contains($lowItem->id));
        $this->assertFalse($itemIds->contains($normalItem->id));
    }

    public function test_total_capital_requires_cost_view_token(): void
    {
        $response = $this->actingAsOwner()
            ->getJson('/api/reports/total-capital');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Verifikasi password diperlukan');
    }

    public function test_total_capital_accessible_with_valid_token(): void
    {
        $verify = $this->actingAsOwner()
            ->postJson('/api/verify-password', ['password' => 'password']);

        $token = $verify->json('data.cost_view_token');

        $response = $this->actingAsOwner()
            ->withHeader('X-Cost-View-Token', $token)
            ->getJson('/api/reports/total-capital');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total_capital', 'per_location'],
            ]);
    }

    public function test_slow_moving_items_returns_items_not_sold_in_60_days(): void
    {
        $item = $this->createCatalogItem();
        $this->stockIn($item, 3);

        $response = $this->actingAsOwner()
            ->getJson('/api/reports/slow-moving-items');

        $response->assertOk();

        $itemIds = collect($response->json('data'))->pluck('item_id');
        $this->assertTrue($itemIds->contains($item->id));
    }

    private function stockIn(Item $item, int $qty): void
    {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/stock-in', [
            'supplier_name' => 'Supplier',
            'transaction_date' => now()->toDateString(),
            'items' => [[
                'barcode' => $item->barcode,
                'qty_received' => $qty,
                'qty_available' => $qty,
                'qty_damaged' => 0,
                'supplier_cost' => 150000,
                'base_margin_type' => 'nominal',
                'base_margin_value' => 50000,
                'base_selling_price' => 200000,
            ]],
        ])->assertCreated();
    }

    private function createCatalogItem(): Item
    {
        return $this->createCatalogItemWithSize('40');
    }

    private function createCatalogItemWithSize(string $sizeName): Item
    {
        $category = Category::where('code', 'SEP')->firstOrFail();
        $brand = Brand::where('name', 'Nike')->firstOrFail();
        $model = ProductModel::where('name', 'Air Max')
            ->where('category_id', $category->id)
            ->where('brand_id', $brand->id)
            ->firstOrFail();
        $color = Color::where('name', 'Hitam')->firstOrFail();
        $size = Size::where('name', $sizeName)->where('size_type', 'shoes')->firstOrFail();

        return app(CatalogService::class)->createCatalogItem([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'color_id' => $color->id,
            'size_id' => $size->id,
        ], User::query()->where('email', 'gudang@aksana.id')->firstOrFail());
    }

    private function actingAsOwner(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'owner@aksana.id')->firstOrFail());

        return $this;
    }
}
