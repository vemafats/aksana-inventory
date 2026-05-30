<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\StockBalanceService;
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

class StockInTest extends TestCase
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

    public function test_can_create_stock_in_transaction(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();

        $response = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode, [
            'qty_received' => 12,
            'qty_available' => 10,
            'qty_damaged' => 2,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_qty_received', 12);

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(10, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertSame(2, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::DAMAGED,
        ));

        $this->assertSame(2, StockMovement::query()->where('reference_type', 'stock_in')->count());
    }

    public function test_stock_increases_after_stock_in(): void
    {
        $item = $this->createCatalogItem();

        $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode, [
            'qty_received' => 5,
            'qty_available' => 5,
            'qty_damaged' => 0,
        ]))->assertCreated();

        $response = $this->actingAsGudang()->getJson('/api/stocks/warehouse');

        $response->assertOk();

        $entry = collect($response->json('data'))
            ->first(fn (array $row): bool => ($row['item']['id'] ?? null) === $item->id);

        $this->assertNotNull($entry);
        $this->assertSame(5, $entry['available']);
    }

    public function test_invalid_barcode_is_rejected(): void
    {
        $response = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload('BARCODE-TIDAK-ADA'));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'tidak ditemukan di katalog',
            $response->json('message') ?? '',
        );
    }

    public function test_qty_mismatch_is_rejected(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode, [
            'qty_received' => 10,
            'qty_available' => 7,
            'qty_damaged' => 2,
        ]));

        $response->assertUnprocessable();
    }

    public function test_supplier_cost_not_in_response(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode));

        $response->assertCreated();

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost', $content);
        $this->assertStringNotContainsString('latest_supplier_cost', $content);
    }

    public function test_stock_movement_audit_trail_created(): void
    {
        $item = $this->createCatalogItem();

        $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode, [
            'qty_received' => 6,
            'qty_available' => 5,
            'qty_damaged' => 1,
        ]))->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::STOCK_IN_AVAILABLE->value,
            'qty' => 5,
            'reference_type' => 'stock_in',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::STOCK_IN_DAMAGED->value,
            'qty' => 1,
            'reference_type' => 'stock_in',
        ]);
    }

    public function test_only_admin_gudang_and_above_can_create_stock_in(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsSales()->postJson('/api/stock-in', $this->stockInPayload($item->barcode));

        $response->assertForbidden();
    }

    public function test_owner_can_update_stock_in_item_price(): void
    {
        $item = $this->createCatalogItem();

        $create = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode, [
            'supplier_cost' => 0,
            'base_margin_type' => 'none',
            'base_margin_value' => 0,
            'base_selling_price' => 0,
        ]));

        $create->assertCreated();

        $transactionId = $create->json('data.id');
        $stockInItemId = $create->json('data.items.0.id');

        $response = $this->actingAsOwner()->putJson(
            "/api/stock-in/{$transactionId}/items/{$stockInItemId}",
            [
                'supplier_cost' => 120000,
                'margin_type' => 'nominal',
                'margin_value' => 30000,
                'qc_note' => 'Harga diisi via web',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.base_selling_price', 150000);

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost', $content);

        $this->assertDatabaseHas('stock_in_items', [
            'id' => $stockInItemId,
            'base_selling_price' => 150000,
        ]);
    }

    public function test_sales_cannot_update_stock_in_item_price(): void
    {
        $item = $this->createCatalogItem();

        $create = $this->actingAsGudang()->postJson('/api/stock-in', $this->stockInPayload($item->barcode));
        $create->assertCreated();

        $transactionId = $create->json('data.id');
        $stockInItemId = $create->json('data.items.0.id');

        $this->actingAsSales()->putJson(
            "/api/stock-in/{$transactionId}/items/{$stockInItemId}",
            [
                'supplier_cost' => 120000,
                'margin_type' => 'nominal',
                'margin_value' => 30000,
            ],
        )->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stockInPayload(string $barcode, array $overrides = []): array
    {
        return [
            'supplier_name' => 'Supplier Test',
            'transaction_date' => now()->toDateString(),
            'note' => 'QC OK',
            'items' => [
                array_merge([
                    'barcode' => $barcode,
                    'qty_received' => 10,
                    'qty_available' => 10,
                    'qty_damaged' => 0,
                    'supplier_cost' => 150000,
                    'base_margin_type' => 'nominal',
                    'base_margin_value' => 50000,
                    'base_selling_price' => 200000,
                    'qc_note' => null,
                ], $overrides),
            ],
        ];
    }

    private function createCatalogItem(): Item
    {
        $category = Category::where('code', 'SEP')->firstOrFail();
        $brand = Brand::where('name', 'Nike')->firstOrFail();
        $model = ProductModel::where('name', 'Air Max')
            ->where('category_id', $category->id)
            ->where('brand_id', $brand->id)
            ->firstOrFail();
        $color = Color::where('name', 'Hitam')->firstOrFail();
        $size = Size::where('name', '40')->where('size_type', 'shoes')->firstOrFail();

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

    private function actingAsGudang(): static
    {
        $login = $this->postJson('/api/login', [
            'email' => 'gudang@aksana.id',
            'password' => 'password',
        ]);

        return $this->withToken($login->json('data.token'));
    }

    private function actingAsSales(): static
    {
        $login = $this->postJson('/api/login', [
            'email' => 'sales@aksana.id',
            'password' => 'password',
        ]);

        return $this->withToken($login->json('data.token'));
    }

    private function actingAsOwner(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'owner@aksana.id')->firstOrFail());

        return $this;
    }
}
