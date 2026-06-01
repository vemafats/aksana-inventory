<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\StockOpnameTransaction;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesActiveEvents;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use CreatesActiveEvents;
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

    public function test_can_create_opname_session(): void
    {
        $warehouse = $this->warehouse();

        $response = $this->actingAsGudang()->postJson('/api/stock-opnames', [
            'location_id' => $warehouse->id,
            'opname_date' => now()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.validation_status', 'draft');
    }

    public function test_cannot_create_second_active_session(): void
    {
        $warehouse = $this->warehouse();

        $this->actingAsGudang()->postJson('/api/stock-opnames', [
            'location_id' => $warehouse->id,
            'opname_date' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAsGudang()->postJson('/api/stock-opnames', [
            'location_id' => $warehouse->id,
            'opname_date' => now()->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'sesi opname aktif',
            strtolower($response->json('message') ?? ''),
        );
    }

    public function test_can_add_item_to_session(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 10);

        $opname = $this->createOpnameSession($warehouse->id);

        $response = $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/items", [
            'item_id' => $item->id,
            'physical_available_qty' => 10,
            'damaged_qty' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.physical_available_qty', 10);
    }

    public function test_submit_changes_status_to_pending_validation(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 5);

        $opname = $this->createOpnameSession($warehouse->id);

        $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/items", [
            'item_id' => $item->id,
            'physical_available_qty' => 5,
            'damaged_qty' => 0,
        ])->assertOk();

        $response = $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.validation_status', 'pending_validation');
    }

    public function test_only_owner_and_admin_can_validate(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 5);

        $opname = $this->createSubmittedOpname($warehouse->id, $item->id);

        $response = $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/validate");

        $response->assertForbidden();
    }

    public function test_validate_increases_stock_when_physical_more_than_system(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 10);

        $opname = $this->createSubmittedOpname($warehouse->id, $item->id, physicalQty: 12);

        $this->actingAsAdmin()->postJson("/api/stock-opnames/{$opname->id}/validate")->assertOk();

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(12, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));
    }

    public function test_validate_decreases_stock_when_physical_less_than_system(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 10);

        $opname = $this->createSubmittedOpname($warehouse->id, $item->id, physicalQty: 8);

        $this->actingAsAdmin()->postJson("/api/stock-opnames/{$opname->id}/validate")->assertOk();

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(8, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertSame(2, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::LOST,
        ));
    }

    public function test_validate_moves_damaged_to_damaged_status(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 10);

        $opname = $this->createOpnameSession($warehouse->id);

        $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/items", [
            'item_id' => $item->id,
            'physical_available_qty' => 8,
            'damaged_qty' => 1,
        ])->assertOk();

        $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/submit")->assertOk();

        $this->actingAsAdmin()->postJson("/api/stock-opnames/{$opname->id}/validate")->assertOk();

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(8, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertSame(1, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::DAMAGED,
        ));
    }

    public function test_reject_does_not_change_stock(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $this->stockIn($item, 10);

        $opname = $this->createSubmittedOpname($warehouse->id, $item->id, physicalQty: 8);

        $this->actingAsAdmin()->postJson("/api/stock-opnames/{$opname->id}/reject", [
            'rejection_note' => 'Data tidak sesuai',
        ])->assertOk();

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(10, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));
    }

    public function test_cannot_close_location_with_remaining_stock(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10);

        $event = $this->activeEventForLocation($bazar);

        $this->actingAsGudang()->postJson('/api/transfers', [
            'from_location_id' => $warehouse->id,
            'event_id' => $event->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $item->id,
                'qty' => 5,
                'bazar_adjust_type' => 'none',
                'bazar_adjust_value' => 0,
                'bazar_selling_price' => 200000,
            ]],
        ])->assertCreated();

        $response = $this->actingAsAdmin()->postJson("/api/locations/{$bazar->id}/close");

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'stok',
            strtolower($response->json('message') ?? ''),
        );
    }

    public function test_can_close_location_when_stock_is_zero(): void
    {
        $location = Location::query()->create([
            'id' => (string) Str::uuid(),
            'location_code' => 'OUT-TEST-001',
            'location_name' => 'Outlet Test Kosong',
            'location_type' => LocationType::OUTLET->value,
            'status' => LocationStatus::ACTIVE->value,
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/locations/{$location->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', LocationStatus::CLOSED->value);
    }

    public function test_sales_cannot_close_location(): void
    {
        $location = $this->emptyActiveLocation();

        $response = $this->actingAsSales()->postJson("/api/locations/{$location->id}/close");

        $response->assertForbidden();
    }

    private function createSubmittedOpname(
        string $locationId,
        string $itemId,
        int $physicalQty = 5,
    ): StockOpnameTransaction {
        $opname = $this->createOpnameSession($locationId);

        $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/items", [
            'item_id' => $itemId,
            'physical_available_qty' => $physicalQty,
            'damaged_qty' => 0,
        ])->assertOk();

        $this->actingAsGudang()->postJson("/api/stock-opnames/{$opname->id}/submit")->assertOk();

        return $opname->fresh();
    }

    private function createOpnameSession(string $locationId): StockOpnameTransaction
    {
        $response = $this->actingAsGudang()->postJson('/api/stock-opnames', [
            'location_id' => $locationId,
            'opname_date' => now()->toDateString(),
        ]);

        $response->assertCreated();

        return StockOpnameTransaction::query()->findOrFail($response->json('data.id'));
    }

    private function emptyActiveLocation(): Location
    {
        return Location::query()->create([
            'id' => (string) Str::uuid(),
            'location_code' => 'OUT-TEST-002',
            'location_name' => 'Outlet Test Penutupan',
            'location_type' => LocationType::OUTLET->value,
            'status' => LocationStatus::ACTIVE->value,
        ]);
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

    private function bazar(): Location
    {
        return Location::query()->where('location_code', 'BAZ-001')->firstOrFail();
    }

    private function actingAsGudang(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        return $this;
    }

    private function actingAsAdmin(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@aksana.id')->firstOrFail());

        return $this;
    }

    private function actingAsSales(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'sales@aksana.id')->firstOrFail());

        return $this;
    }
}
