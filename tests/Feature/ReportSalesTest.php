<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogService;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\LocationAssignmentSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportSalesTest extends TestCase
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
            EmployeeSeeder::class,
            LocationSeeder::class,
            LocationAssignmentSeeder::class,
        ]);
    }

    public function test_gross_profit_calculated_correctly(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, supplierCost: 100000, baseSellingPrice: 150000);
        $this->transferToBazar($item, $warehouse, $bazar, 10, bazarSellingPrice: 150000);

        $this->createSale($bazar, 'EMP002', [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]);
        $this->createSale($bazar, 'EMP002', [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]);

        $response = $this->actingAsOwner()->getJson('/api/reports/gross-profit?'.http_build_query([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('data.total_sales', 300000)
            ->assertJsonPath('data.total_cogs', 200000)
            ->assertJsonPath('data.gross_profit', 100000);
    }

    public function test_best_selling_products_ranked_correctly(): void
    {
        $itemA = $this->createCatalogItem();
        $itemB = $this->createCatalogItemWithSize('41');
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        foreach ([$itemA, $itemB] as $item) {
            $this->stockIn($item, 20);
            $this->transferToBazar($item, $warehouse, $bazar, 20);
        }

        $this->createSale($bazar, 'EMP002', [['item_id' => $itemA->id, 'qty' => 5]]);
        $this->createSale($bazar, 'EMP002', [['item_id' => $itemB->id, 'qty' => 3]]);

        $response = $this->actingAsOwner()->getJson('/api/reports/best-selling-products?'.http_build_query([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();

        $rows = collect($response->json('data'));
        $this->assertSame(1, $rows->firstWhere('item_id', $itemA->id)['rank']);
        $this->assertSame(5, $rows->firstWhere('item_id', $itemA->id)['total_qty_sold']);
        $this->assertSame(2, $rows->firstWhere('item_id', $itemB->id)['rank']);
        $this->assertSame(3, $rows->firstWhere('item_id', $itemB->id)['total_qty_sold']);

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost', $content);
    }

    public function test_sales_by_location_returns_breakdown(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazarOne = $this->bazar();
        $bazarTwo = $this->secondBazar();

        $this->stockIn($item, 30);
        $this->transferToBazar($item, $warehouse, $bazarOne, 10, bazarSellingPrice: 150000);
        $this->transferToBazar($item, $warehouse, $bazarTwo, 10, bazarSellingPrice: 200000);

        $this->createSale($bazarOne, 'EMP002', [['item_id' => $item->id, 'qty' => 1]]);
        $this->createSale($bazarTwo, 'EMP004', [['item_id' => $item->id, 'qty' => 1]]);

        $response = $this->actingAsOwner()->getJson('/api/reports/sales-by-location?'.http_build_query([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();

        $rows = collect($response->json('data'));
        $this->assertTrue($rows->contains('location_id', $bazarOne->id));
        $this->assertTrue($rows->contains('location_id', $bazarTwo->id));
        $this->assertEquals(150000, (float) $rows->firstWhere('location_id', $bazarOne->id)['total_sales']);
        $this->assertEquals(200000, (float) $rows->firstWhere('location_id', $bazarTwo->id)['total_sales']);
    }

    public function test_mobile_summary_returns_correct_structure(): void
    {
        $response = $this->actingAsPicBazar()
            ->getJson('/api/reports/mobile-summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'todays_net_sales',
                    'todays_transactions',
                    'items_sold_today',
                    'avg_basket_today',
                    'vs_yesterday_pct',
                    'seven_day_trend',
                    'top_sku_today',
                    'low_stock_count',
                ],
            ]);
    }

    public function test_gross_profit_supplier_cost_never_exposed(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5, supplierCost: 100000, baseSellingPrice: 150000);
        $this->transferToBazar($item, $warehouse, $bazar, 5, bazarSellingPrice: 150000);
        $this->createSale($bazar, 'EMP002', [['item_id' => $item->id, 'qty' => 1]]);

        $response = $this->actingAsOwner()->getJson('/api/reports/gross-profit?'.http_build_query([
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost_snapshot', $content);
        $this->assertStringNotContainsString('supplier_cost', $content);
        $this->assertStringContainsString('total_cogs', $content);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function createSale(Location $location, string $employeeCode, array $items): void
    {
        $email = match ($employeeCode) {
            'EMP002' => 'picbazar@aksana.id',
            'EMP003' => 'sales@aksana.id',
            'EMP004' => 'maya@aksana.id',
            default => 'picbazar@aksana.id',
        };

        Sanctum::actingAs(User::query()->where('email', $email)->firstOrFail());

        $this->postJson('/api/sales', [
            'location_id' => $location->id,
            'transaction_date' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'transaction_discount_type' => 'none',
            'transaction_discount_value' => 0,
            'items' => array_map(fn (array $line) => array_merge([
                'item_discount_type' => 'none',
                'item_discount_value' => 0,
            ], $line), $items),
        ])->assertCreated();
    }

    private function stockIn(
        Item $item,
        int $qty,
        float $supplierCost = 150000,
        float $baseSellingPrice = 200000,
    ): void {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/stock-in', [
            'supplier_name' => 'Supplier',
            'transaction_date' => now()->toDateString(),
            'items' => [[
                'barcode' => $item->barcode,
                'qty_received' => $qty,
                'qty_available' => $qty,
                'qty_damaged' => 0,
                'supplier_cost' => $supplierCost,
                'base_margin_type' => 'nominal',
                'base_margin_value' => $baseSellingPrice - $supplierCost,
                'base_selling_price' => $baseSellingPrice,
            ]],
        ])->assertCreated();
    }

    private function transferToBazar(
        Item $item,
        Location $warehouse,
        Location $bazar,
        int $qty,
        float $bazarSellingPrice = 200000,
    ): void {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/transfers', [
            'from_location_id' => $warehouse->id,
            'to_location_id' => $bazar->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $item->id,
                'qty' => $qty,
                'bazar_adjust_type' => 'manual',
                'bazar_adjust_value' => $bazarSellingPrice,
                'bazar_selling_price' => $bazarSellingPrice,
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

    private function warehouse(): Location
    {
        return Location::query()->where('location_code', 'GUD-001')->firstOrFail();
    }

    private function bazar(): Location
    {
        return Location::query()->where('location_code', 'BAZ-001')->firstOrFail();
    }

    private function secondBazar(): Location
    {
        $location = Location::query()->where('location_code', 'BAZ-002')->firstOrFail();
        $location->update(['status' => LocationStatus::ACTIVE->value]);

        return $location->fresh();
    }

    private function actingAsOwner(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'owner@aksana.id')->firstOrFail());

        return $this;
    }

    private function actingAsPicBazar(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'picbazar@aksana.id')->firstOrFail());

        return $this;
    }
}
