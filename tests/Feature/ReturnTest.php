<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\EventService;
use App\Services\StockBalanceService;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesActiveEvents;
use Tests\TestCase;

class ReturnTest extends TestCase
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

    public function test_can_create_return_via_api_with_event(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();
        $event = $this->activeEventForLocation($bazar);

        $this->stockIn($item, 10);
        $this->transferToBazar($item, $warehouse, $bazar, 5);

        $response = $this->actingAsPicBazar()->postJson('/api/returns', [
            'event_id' => $event->id,
            'return_date' => now()->toDateString(),
            'note' => 'Return sisa event',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty_good' => 3,
                    'qty_damaged' => 0,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Return berhasil');

        $this->assertDatabaseHas('transfer_transactions', [
            'event_id' => $event->id,
            'from_location_id' => $bazar->id,
            'to_location_id' => $warehouse->id,
        ]);

        $balanceService = app(StockBalanceService::class);
        $this->assertSame(2, $balanceService->getBalance($item->id, $bazar->id, \App\Enums\StockStatus::AVAILABLE));
        $this->assertSame(8, $balanceService->getBalance($item->id, $warehouse->id, \App\Enums\StockStatus::AVAILABLE));
    }

    public function test_sales_cannot_create_return(): void
    {
        $bazar = $this->bazar();
        $event = $this->activeEventForLocation($bazar);
        $item = $this->createCatalogItem();

        Sanctum::actingAs(User::query()->where('email', 'sales@aksana.id')->firstOrFail());

        $response = $this->postJson('/api/returns', [
            'event_id' => $event->id,
            'return_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'qty_good' => 1, 'qty_damaged' => 0],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_end_event_with_remaining_stock(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        $event = app(EventService::class)->createEvent([
            'location_id' => $bazar->id,
            'name' => 'Event With Stock',
            'start_date' => Carbon::today('Asia/Jakarta')->toDateString(),
            'end_date' => Carbon::today('Asia/Jakarta')->addDays(3)->toDateString(),
            'status' => 'active',
            'assignments' => [
                [
                    'user_id' => User::query()->where('email', 'sales@aksana.id')->firstOrFail()->id,
                    'role_in_event' => 'sales',
                ],
            ],
        ], $owner);

        $this->stockIn($item, 10);
        $this->transferToBazar($item, $warehouse, $bazar, 4);

        $response = $this->actingAsOwner()->postJson("/api/events/{$event->id}/end");

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'Sisa stok:',
            $response->json('message') ?? '',
        );

        $this->assertSame('active', $event->fresh()->status);
    }

    public function test_can_end_event_when_stock_is_cleared(): void
    {
        $item = $this->createCatalogItem();
        $warehouse = $this->warehouse();
        $bazar = $this->bazar();
        $owner = User::query()->where('email', 'owner@aksana.id')->firstOrFail();

        $event = app(EventService::class)->createEvent([
            'location_id' => $bazar->id,
            'name' => 'Event Empty',
            'start_date' => Carbon::today('Asia/Jakarta')->toDateString(),
            'end_date' => Carbon::today('Asia/Jakarta')->addDays(3)->toDateString(),
            'status' => 'active',
            'assignments' => [
                [
                    'user_id' => User::query()->where('email', 'sales@aksana.id')->firstOrFail()->id,
                    'role_in_event' => 'sales',
                ],
            ],
        ], $owner);

        $response = $this->actingAsOwner()->postJson("/api/events/{$event->id}/end");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ended');
    }

    private function stockIn(Item $item, int $qty): void
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
                    'base_selling_price' => 200000,
                ],
            ],
        ])->assertCreated();
    }

    private function transferToBazar(Item $item, Location $warehouse, Location $bazar, int $qty): void
    {
        $event = $this->activeEventForLocation($bazar);

        Sanctum::actingAs(User::query()->where('email', 'gudang@aksana.id')->firstOrFail());

        $this->postJson('/api/transfers', [
            'from_location_id' => $warehouse->id,
            'event_id' => $event->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $item->id,
                'qty' => $qty,
                'bazar_adjust_type' => 'none',
                'bazar_adjust_value' => 0,
                'bazar_selling_price' => 200000,
            ]],
        ])->assertCreated();
    }

    private function createCatalogItem(): Item
    {
        $category = \App\Models\Category::where('code', 'SEP')->firstOrFail();
        $brand = \App\Models\Brand::where('name', 'Nike')->firstOrFail();
        $model = \App\Models\ProductModel::where('name', 'Air Max')
            ->where('category_id', $category->id)
            ->where('brand_id', $brand->id)
            ->firstOrFail();
        $color = \App\Models\Color::where('name', 'Hitam')->firstOrFail();
        $size = \App\Models\Size::where('name', '40')->where('size_type', 'shoes')->firstOrFail();

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

    private function actingAsOwner(): static
    {
        Sanctum::actingAs(User::query()->where('email', 'owner@aksana.id')->firstOrFail());

        return $this;
    }
}
