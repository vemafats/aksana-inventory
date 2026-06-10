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
use App\Models\TransferItem;
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
use Tests\Concerns\CreatesActiveEvents;
use Tests\TestCase;

class TransferTest extends TestCase
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

    public function test_can_transfer_stock_from_warehouse_to_bazar(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10);

        $response = $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
            ['qty' => 6],
        ));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $balanceService = app(StockBalanceService::class);

        $this->assertSame(4, $balanceService->getBalance(
            $item->id,
            $warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertSame(6, $balanceService->getBalance(
            $item->id,
            $bazar->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => MovementType::TRANSFER_AVAILABLE->value,
            'qty' => 6,
            'reference_type' => 'transfer',
        ]);

        $event = $this->activeEventForLocation($bazar);

        $this->assertDatabaseHas('transfer_transactions', [
            'to_location_id' => $bazar->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_transfer_fails_when_insufficient_stock(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5);

        $response = $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
            ['qty' => 10],
        ));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'Stok tidak cukup',
            $response->json('message') ?? '',
        );
    }

    public function test_price_snapshot_saved_correctly(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, baseSellingPrice: 275000);

        $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
        ))->assertCreated();

        $transferItem = TransferItem::query()->where('item_id', $item->id)->firstOrFail();

        $this->assertEquals(275000, (float) $transferItem->base_selling_price_snapshot);
    }

    public function test_supplier_cost_not_in_transfer_response(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10);

        $response = $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
        ));

        $response->assertCreated();

        $content = $response->getContent();
        $this->assertStringNotContainsString('supplier_cost_snapshot', $content);
        $this->assertStringNotContainsString('supplier_cost', $content);
    }

    public function test_stock_movement_created_for_transfer(): void
    {
        $itemOne = $this->createCatalogItem();
        $itemTwo = $this->createCatalogItemWithSize('41');
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($itemOne, 10);
        $this->stockIn($itemTwo, 8);

        $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $itemOne->id,
            ['qty' => 2],
        ))->assertCreated();

        $this->actingAsGudang()->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $itemTwo->id,
            ['qty' => 3],
        ))->assertCreated();

        $this->assertSame(2, StockMovement::query()
            ->where('reference_type', 'transfer')
            ->where('movement_type', MovementType::TRANSFER_AVAILABLE->value)
            ->count());
    }

    public function test_cannot_transfer_to_same_location(): void
    {
        $item = $this->createCatalogItem();
        $bazar = $this->bazar();
        $event = $this->activeEventForLocation($bazar);

        $this->stockIn($item, 5);

        $response = $this->actingAsGudang()->postJson('/api/transfers', array_merge(
            $this->transferPayload($bazar->id, $bazar->id, $item->id),
            ['event_id' => $event->id],
        ));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_only_authorized_roles_can_transfer(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 5);

        $salesUser = User::query()->where('email', 'sales@aksana.id')->firstOrFail();
        $this->assertFalse($salesUser->role->canTransfer());

        Sanctum::actingAs($salesUser);

        $response = $this->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
        ));

        $response->assertForbidden();
    }

    private function stockIn(Item $item, int $qty, float $baseSellingPrice = 200000): void
    {
        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/stock-in', [
            'supplier_name' => 'Supplier',
            'transaction_date' => now()->toDateString(),
            'items' => [
                [
                    'barcode' => $item->barcode,
                    'qty_received' => $qty,
                    'qty_available' => $qty,
                    'qty_damaged' => 0,
                    'supplier_cost' => 150000,
                    'base_margin_type' => 'nominal',
                    'base_margin_value' => 50000,
                    'base_selling_price' => $baseSellingPrice,
                ],
            ],
        ])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $itemOverrides
     * @return array<string, mixed>
     */
    private function transferPayload(
        string $fromLocationId,
        string $toLocationId,
        string $itemId,
        array $itemOverrides = [],
    ): array {
        $toLocation = Location::query()->findOrFail($toLocationId);
        $event = $this->activeEventForLocation($toLocation);

        return [
            'from_location_id' => $fromLocationId,
            'event_id' => $event->id,
            'transfer_date' => now()->toDateString(),
            'note' => null,
            'items' => [
                $this->transferItemPayload($itemId, $itemOverrides),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function transferItemPayload(string $itemId, array $overrides = []): array
    {
        return array_merge([
            'item_id' => $itemId,
            'qty' => 1,
            'bazar_adjust_type' => 'none',
            'bazar_adjust_value' => 0,
            'bazar_selling_price' => 200000,
        ], $overrides);
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
        $size = Size::where('name', $sizeName)->where('category_id', $category->id)->firstOrFail();

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
}
