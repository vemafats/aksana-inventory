<?php

namespace Tests\Unit;

use App\Enums\StockStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\StockBalance;
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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockBalanceService $service;

    private Item $item;

    private Location $warehouse;

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

        $this->service = app(StockBalanceService::class);
        $this->item = $this->createCatalogItem();
        $this->warehouse = Location::query()
            ->where('location_code', 'GUD-001')
            ->firstOrFail();
    }

    public function test_get_balance_returns_zero_when_record_not_found(): void
    {
        $this->assertSame(0, $this->service->getBalance(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
        ));
    }

    public function test_increase_creates_and_adds_stock(): void
    {
        DB::transaction(function (): void {
            $balance = $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                5,
            );

            $this->assertSame(5, $balance->qty);
        });

        $this->assertSame(5, $this->service->getBalance(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->item->id,
            'location_id' => $this->warehouse->id,
            'stock_status' => StockStatus::AVAILABLE->value,
            'qty' => 5,
        ]);
    }

    public function test_decrease_reduces_stock(): void
    {
        DB::transaction(function (): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                10,
            );

            $balance = $this->service->decrease(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                3,
            );

            $this->assertSame(7, $balance->qty);
        });

        $this->assertSame(7, $this->service->getBalance(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
        ));
    }

    public function test_decrease_throws_when_stock_insufficient(): void
    {
        $this->expectException(InsufficientStockException::class);

        DB::transaction(function (): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                2,
            );

            $this->service->decrease(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                5,
            );
        });
    }

    public function test_validate_enough_stock(): void
    {
        $this->assertFalse($this->service->validateEnoughStock(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
            1,
        ));

        DB::transaction(function (): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                4,
            );
        });

        $this->assertTrue($this->service->validateEnoughStock(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
            4,
        ));

        $this->assertFalse($this->service->validateEnoughStock(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
            5,
        ));
    }

    public function test_move_transfers_stock_between_locations(): void
    {
        $bazar = Location::query()
            ->where('location_code', 'BAZ-001')
            ->firstOrFail();

        DB::transaction(function () use ($bazar): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                8,
            );

            $this->service->move(
                $this->item->id,
                $this->warehouse->id,
                $bazar->id,
                StockStatus::AVAILABLE,
                StockStatus::AVAILABLE,
                3,
            );
        });

        $this->assertSame(5, $this->service->getBalance(
            $this->item->id,
            $this->warehouse->id,
            StockStatus::AVAILABLE,
        ));

        $this->assertSame(3, $this->service->getBalance(
            $this->item->id,
            $bazar->id,
            StockStatus::AVAILABLE,
        ));
    }

    public function test_get_balances_by_location_groups_by_item(): void
    {
        DB::transaction(function (): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                2,
            );

            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::DAMAGED,
                1,
            );
        });

        $grouped = $this->service->getBalancesByLocation($this->warehouse->id);

        $this->assertTrue($grouped->has($this->item->id));
        $this->assertCount(2, $grouped->get($this->item->id));
        $this->assertTrue($grouped->first()->first()->relationLoaded('item'));
        $this->assertTrue($grouped->first()->first()->item->relationLoaded('category'));
    }

    public function test_get_balances_by_item_eager_loads_location(): void
    {
        DB::transaction(function (): void {
            $this->service->increase(
                $this->item->id,
                $this->warehouse->id,
                StockStatus::AVAILABLE,
                1,
            );
        });

        $balances = $this->service->getBalancesByItem($this->item->id);

        $this->assertCount(1, $balances);
        $this->assertInstanceOf(StockBalance::class, $balances->first());
        $this->assertTrue($balances->first()->relationLoaded('location'));
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
        ], User::query()->firstOrFail());
    }
}
