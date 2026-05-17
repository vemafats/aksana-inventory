<?php

namespace Tests\Unit;

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\StockInItem;
use App\Models\StockInTransaction;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\StockMovementService;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockMovementService $service;

    private User $user;

    private Item $item;

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

        $this->service = app(StockMovementService::class);
        $this->user = User::query()->firstOrFail();
        $this->item = $this->createCatalogItem();
    }

    public function test_generates_unique_movement_number(): void
    {
        $warehouse = Location::query()->where('location_code', 'GUD-001')->firstOrFail();

        [$first, $second] = DB::transaction(function () use ($warehouse): array {
            $basePayload = [
                'movement_type' => MovementType::STOCK_IN_AVAILABLE,
                'item_id' => $this->item->id,
                'qty' => 1,
                'to_location_id' => $warehouse->id,
                'to_stock_status' => StockStatus::AVAILABLE->value,
                'reference_type' => 'stock_in',
                'created_by' => $this->user->id,
            ];

            $firstMovement = $this->service->createMovement([
                ...$basePayload,
                'reference_id' => $this->item->id,
            ]);

            $secondMovement = $this->service->createMovement([
                ...$basePayload,
                'reference_id' => $warehouse->id,
            ]);

            return [$firstMovement->movement_number, $secondMovement->movement_number];
        });

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^SM-\d{8}-\d{5}$/', $first);
        $this->assertMatchesRegularExpression('/^SM-\d{8}-\d{5}$/', $second);
    }

    public function test_movement_number_format_is_correct(): void
    {
        $number = DB::transaction(fn (): string => $this->service->generateMovementNumber());

        $this->assertStringStartsWith('SM-', $number);
        $this->assertMatchesRegularExpression('/^SM-\d{8}-\d{5}$/', $number);
        $this->assertSame(17, strlen($number));
    }

    public function test_can_create_movement_record(): void
    {
        $warehouse = Location::query()->where('location_code', 'GUD-001')->firstOrFail();

        $movement = DB::transaction(fn (): StockMovement => $this->service->createMovement([
            'movement_type' => MovementType::STOCK_IN_AVAILABLE,
            'item_id' => $this->item->id,
            'qty' => 3,
            'to_location_id' => $warehouse->id,
            'to_stock_status' => StockStatus::AVAILABLE->value,
            'reference_type' => 'stock_in',
            'reference_id' => $this->item->id,
            'created_by' => $this->user->id,
        ]));

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'item_id' => $this->item->id,
            'qty' => 3,
        ]);

        $this->assertNotNull($movement->movement_number);
        $this->assertNotNull($movement->created_at);
        $this->assertSame(MovementType::STOCK_IN_AVAILABLE, $movement->movement_type);
    }

    public function test_movement_records_are_immutable(): void
    {
        $warehouse = Location::query()->where('location_code', 'GUD-001')->firstOrFail();

        $movement = DB::transaction(fn (): StockMovement => $this->service->createMovement([
            'movement_type' => MovementType::SALE,
            'item_id' => $this->item->id,
            'qty' => 1,
            'from_location_id' => $warehouse->id,
            'from_stock_status' => StockStatus::AVAILABLE->value,
            'reference_type' => 'sale',
            'reference_id' => $this->item->id,
            'created_by' => $this->user->id,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $movement->delete();
    }

    public function test_create_for_stock_in_sets_correct_fields(): void
    {
        $warehouse = Location::query()->where('location_code', 'GUD-001')->firstOrFail();

        $transaction = StockInTransaction::query()->create([
            'transaction_number' => 'SI-TEST-001',
            'supplier_name' => 'Supplier Test',
            'transaction_date' => now()->toDateString(),
            'total_qty_received' => 5,
            'total_qty_available' => 5,
            'total_qty_damaged' => 0,
            'created_by' => $this->user->id,
        ]);

        $stockInItem = StockInItem::query()->create([
            'stock_in_transaction_id' => $transaction->id,
            'item_id' => $this->item->id,
            'qty_received' => 5,
            'qty_available' => 5,
            'qty_damaged' => 0,
            'supplier_cost' => 100000,
            'base_margin_type' => 'nominal',
            'base_margin_value' => 50000,
            'base_selling_price' => 150000,
        ]);

        $movement = DB::transaction(fn (): StockMovement => $this->service->createForStockIn(
            $transaction,
            $stockInItem,
            StockStatus::AVAILABLE,
            5,
            $this->user,
        ));

        $this->assertSame(MovementType::STOCK_IN_AVAILABLE, $movement->movement_type);
        $this->assertSame('stock_in', $movement->reference_type);
        $this->assertSame($transaction->id, $movement->reference_id);
        $this->assertNull($movement->from_location_id);
        $this->assertSame($warehouse->id, $movement->to_location_id);
        $this->assertSame(StockStatus::AVAILABLE->value, $movement->to_stock_status);
        $this->assertSame(5, $movement->qty);
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
        ], $this->user);
    }
}
