<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\StockBalance;
use App\Models\StockInTransaction;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\StockInService;
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

class PricePropagationTest extends TestCase
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

    public function test_price_update_propagates_nominal_adjustment_to_active_transfer_items(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, baseSellingPrice: 500000);

        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
            [
                'qty' => 6,
                'bazar_adjust_type' => 'nominal',
                'bazar_adjust_value' => 50000,
                'bazar_selling_price' => 550000,
            ],
        ))->assertCreated();

        $transferItem = TransferItem::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertEquals(500000, (float) $transferItem->base_selling_price_snapshot);
        $this->assertEquals(550000, (float) $transferItem->bazar_selling_price);

        $stockIn = StockInTransaction::query()->with('stockInItems')->firstOrFail();
        $stockInItem = $stockIn->stockInItems->firstOrFail();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        app(StockInService::class)->updateItemPrice(
            $stockIn,
            $stockInItem,
            550000,
            'nominal',
            50000,
        );

        $transferItem->refresh();
        $item->refresh();

        $this->assertEquals(600000, (float) $item->latest_base_selling_price);
        $this->assertEquals(600000, (float) $transferItem->base_selling_price_snapshot);
        $this->assertEquals(650000, (float) $transferItem->bazar_selling_price);
    }

    public function test_manual_transfer_price_is_not_changed_on_item_price_update(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, baseSellingPrice: 500000);

        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
            [
                'qty' => 4,
                'bazar_adjust_type' => 'manual',
                'bazar_adjust_value' => 0,
                'bazar_selling_price' => 575000,
            ],
        ))->assertCreated();

        $transferItem = TransferItem::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertEquals(575000, (float) $transferItem->bazar_selling_price);

        $stockIn = StockInTransaction::query()->with('stockInItems')->firstOrFail();
        $stockInItem = $stockIn->stockInItems->firstOrFail();

        app(StockInService::class)->updateItemPrice(
            $stockIn,
            $stockInItem,
            550000,
            'nominal',
            50000,
        );

        $transferItem->refresh();

        $this->assertEquals(575000, (float) $transferItem->bazar_selling_price);
        $this->assertEquals(500000, (float) $transferItem->base_selling_price_snapshot);
    }

    public function test_transfer_items_not_updated_when_destination_has_no_stock(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();

        $this->stockIn($item, 10, baseSellingPrice: 500000);

        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/transfers', $this->transferPayload(
            $warehouse->id,
            $bazar->id,
            $item->id,
            [
                'qty' => 3,
                'bazar_adjust_type' => 'nominal',
                'bazar_adjust_value' => 50000,
                'bazar_selling_price' => 550000,
            ],
        ))->assertCreated();

        StockBalance::query()
            ->where('item_id', $item->id)
            ->where('location_id', $bazar->id)
            ->update(['qty' => 0]);

        $stockIn = StockInTransaction::query()->with('stockInItems')->firstOrFail();
        $stockInItem = $stockIn->stockInItems->firstOrFail();

        app(StockInService::class)->updateItemPrice(
            $stockIn,
            $stockInItem,
            550000,
            'nominal',
            50000,
        );

        $transferItem = TransferItem::query()->where('item_id', $item->id)->firstOrFail();

        $this->assertEquals(500000, (float) $transferItem->base_selling_price_snapshot);
        $this->assertEquals(550000, (float) $transferItem->bazar_selling_price);
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
                    'supplier_cost' => $baseSellingPrice - 50000,
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
                array_merge([
                    'item_id' => $itemId,
                    'qty' => 1,
                    'bazar_adjust_type' => 'none',
                    'bazar_adjust_value' => 0,
                    'bazar_selling_price' => 200000,
                ], $itemOverrides),
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
        $size = Size::where('name', '40')->where('category_id', $category->id)->firstOrFail();

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
}
