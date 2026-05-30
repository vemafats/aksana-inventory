<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\PriceCalculationService;
use App\Services\StockBalanceService;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesTest extends TestCase
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

    public function test_can_create_sales_transaction(): void
    {
        $itemOne = $this->createCatalogItem();
        $itemTwo = $this->createCatalogItemWithSize('41');
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($itemOne, 10);
        $this->stockIn($itemTwo, 10);
        $this->transferToBazar($itemOne, $warehouse, $bazar, 6);
        $this->transferToBazar($itemTwo, $warehouse, $bazar, 6);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, [
            [
                'item_id' => $itemOne->id,
                'qty' => 1,
            ],
            [
                'item_id' => $itemTwo->id,
                'qty' => 1,
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(5, $balanceService->getBalance($itemOne->id, $bazar->id, StockStatus::AVAILABLE));
        $this->assertSame(5, $balanceService->getBalance($itemTwo->id, $bazar->id, StockStatus::AVAILABLE));

        $this->assertGreaterThan(0, (float) $response->json('data.grand_total'));

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => MovementType::SALE->value,
            'reference_type' => 'sale',
        ]);
    }

    public function test_backend_recalculates_prices(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, supplierCost: 100000, baseSellingPrice: 200000);
        $this->transferToBazar($item, $warehouse, $bazar, 5, bazarSellingPrice: 200000);

        $payload = $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 2,
            'item_discount_type' => 'percentage',
            'item_discount_value' => 10,
        ]], transactionDiscountType: 'none', transactionDiscountValue: 0);

        $lineTotals = app(PriceCalculationService::class)->calculateSalesItemTotals([
            'selling_price' => 200000,
            'qty' => 2,
            'item_discount_type' => 'percentage',
            'item_discount_value' => 10,
            'supplier_cost_snapshot' => 100000,
        ]);

        $expectedGrandTotal = app(PriceCalculationService::class)->calculateTransactionTotals(
            [$lineTotals],
            'none',
            0,
        )['grand_total'];

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $payload);

        $response->assertCreated();
        $this->assertSame($expectedGrandTotal, (float) $response->json('data.grand_total'));
    }

    public function test_sales_fails_when_stock_insufficient(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10);
        $this->transferToBazar($item, $warehouse, $bazar, 2);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 5,
        ]]));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'Stok tidak cukup',
            $response->json('message') ?? '',
        );
    }

    public function test_sales_fails_for_inactive_location(): void
    {
        $item = $this->createCatalogItem();
        $location = Location::query()->create([
            'id' => (string) Str::uuid(),
            'location_code' => 'BAZ-CLOSED',
            'location_name' => 'Bazar Tutup',
            'location_type' => LocationType::BAZAR->value,
            'status' => LocationStatus::CLOSED->value,
        ]);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($location->id, [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]));

        $response->assertUnprocessable();

        $this->assertStringContainsString(
            'tidak aktif',
            strtolower($response->json('message') ?? ''),
        );
    }

    public function test_gross_profit_calculated_correctly(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5, supplierCost: 100000, baseSellingPrice: 150000);
        $this->transferToBazar($item, $warehouse, $bazar, 5, bazarSellingPrice: 150000);

        $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]))->assertCreated();

        $salesItem = SalesItem::query()->where('item_id', $item->id)->firstOrFail();

        $this->assertEquals(50000, (float) $salesItem->gross_profit);
    }

    public function test_supplier_cost_never_in_response(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5);
        $this->transferToBazar($item, $warehouse, $bazar, 5);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]));

        $response->assertCreated();

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost_snapshot', $content);
        $this->assertStringNotContainsString('supplier_cost', $content);
        $this->assertStringNotContainsString('latest_supplier_cost', $content);
    }

    public function test_discount_applied_correctly(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5, baseSellingPrice: 200000);
        $this->transferToBazar($item, $warehouse, $bazar, 5, bazarSellingPrice: 200000);

        $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 1,
            'item_discount_type' => 'percentage',
            'item_discount_value' => 10,
        ]]))->assertCreated();

        $salesItem = SalesItem::query()->where('item_id', $item->id)->firstOrFail();

        $this->assertEquals(20000, (float) $salesItem->item_discount_amount);
        $this->assertEquals(180000, (float) $salesItem->total_after_discount);
    }

    public function test_transaction_discount_applied_after_item_discounts(): void
    {
        $itemOne = $this->createCatalogItem();
        $itemTwo = $this->createCatalogItemWithSize('41');
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($itemOne, 5, baseSellingPrice: 250000);
        $this->stockIn($itemTwo, 5, baseSellingPrice: 250000);
        $this->transferToBazar($itemOne, $warehouse, $bazar, 5, bazarSellingPrice: 250000);
        $this->transferToBazar($itemTwo, $warehouse, $bazar, 5, bazarSellingPrice: 250000);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload(
            $bazar->id,
            [
                ['item_id' => $itemOne->id, 'qty' => 1],
                ['item_id' => $itemTwo->id, 'qty' => 1],
            ],
            transactionDiscountType: 'percentage',
            transactionDiscountValue: 10,
        ));

        $response->assertCreated();

        $transaction = SalesTransaction::query()->findOrFail($response->json('data.id'));

        $this->assertEquals(500000, (float) $transaction->total_after_item_discount);
        $this->assertEquals(450000, (float) $transaction->grand_total);
    }

    public function test_stock_movement_created_for_each_item(): void
    {
        $items = [
            $this->createCatalogItem(),
            $this->createCatalogItemWithSize('41'),
            $this->createCatalogItemWithSize('42'),
        ];
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        foreach ($items as $item) {
            $this->stockIn($item, 5);
            $this->transferToBazar($item, $warehouse, $bazar, 5);
        }

        $lines = array_map(fn (Item $item) => ['item_id' => $item->id, 'qty' => 1], $items);

        $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload($bazar->id, $lines))->assertCreated();

        $this->assertSame(3, StockMovement::query()
            ->where('movement_type', MovementType::SALE->value)
            ->count());
    }

    public function test_only_authorized_roles_can_sell(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5);
        $this->transferToBazar($item, $warehouse, $bazar, 5);

        $response = $this->actingAsGudang()->postJson('/api/sales', $this->salesPayload($bazar->id, [[
            'item_id' => $item->id,
            'qty' => 1,
        ]]));

        $response->assertForbidden();
    }

    public function test_payment_method_saved_correctly(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5);
        $this->transferToBazar($item, $warehouse, $bazar, 5);

        $response = $this->actingAsPicBazar()->postJson('/api/sales', $this->salesPayload(
            $bazar->id,
            [['item_id' => $item->id, 'qty' => 1]],
            paymentMethod: 'qris',
        ));

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'qris');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function salesPayload(
        string $locationId,
        array $items,
        string $transactionDiscountType = 'none',
        float $transactionDiscountValue = 0,
        string $paymentMethod = 'cash',
    ): array {
        return [
            'location_id' => $locationId,
            'transaction_date' => now()->toDateTimeString(),
            'payment_method' => $paymentMethod,
            'transaction_discount_type' => $transactionDiscountType,
            'transaction_discount_value' => $transactionDiscountValue,
            'items' => array_map(fn (array $line) => array_merge([
                'item_discount_type' => 'none',
                'item_discount_value' => 0,
            ], $line), $items),
        ];
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

    private function actingAsPicBazar(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'picbazar@aksana.id')->firstOrFail());

        return $this;
    }

    private function actingAsGudang(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        return $this;
    }
}
