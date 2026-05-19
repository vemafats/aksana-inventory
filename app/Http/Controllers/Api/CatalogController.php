<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogRequest;
use App\Http\Requests\Catalog\UpdateCatalogRequest;
use App\Models\Item;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()
            ->select([
                'id',
                'category_id',
                'brand_id',
                'model_id',
                'color_id',
                'size_id',
                'sku',
                'barcode',
                'item_name',
                'catalog_photo_path',
                'latest_base_selling_price',
                'description',
                'is_active',
                'created_at',
            ])
            ->with([
                'category',
                'brand',
                'productModel',
                'color',
                'size',
                'stockBalances' => fn ($query) => $query->select([
                    'id',
                    'item_id',
                    'location_id',
                    'stock_status',
                    'qty',
                ]),
                'stockBalances.location:id,location_name,location_code,location_type',
            ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->string('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->string('brand_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('item_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $items = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load([
            'category',
            'brand',
            'productModel',
            'color',
            'size',
            'stockBalances.location',
        ]);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $item->toArray(),
                ['stock_summary' => $this->buildStockSummary($item)],
            ),
        ]);
    }

    public function store(StoreCatalogRequest $request): JsonResponse
    {
        $item = $this->catalogService->createCatalogItem(
            $request->validated(),
            $request->user(),
        );

        $item->load(['category', 'brand', 'productModel', 'color', 'size']);

        return response()->json([
            'success' => true,
            'message' => 'Item katalog berhasil dibuat',
            'data' => $item,
        ], 201);
    }

    public function update(UpdateCatalogRequest $request, Item $item): JsonResponse
    {
        $item = $this->catalogService->updateCatalogItem($item, $request->validated());
        $item->load(['category', 'brand', 'productModel', 'color', 'size']);

        return response()->json([
            'success' => true,
            'message' => 'Item katalog berhasil diperbarui',
            'data' => $item,
        ]);
    }

    public function findByBarcode(string $barcode): JsonResponse
    {
        $item = Item::query()
            ->where('barcode', $barcode)
            ->with([
                'category',
                'brand',
                'productModel',
                'color',
                'size',
                'stockBalances.location',
            ])
            ->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode tidak ditemukan. Buat katalog dulu di web admin.',
                'barcode' => $barcode,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $item->toArray(),
                ['stock_summary' => $this->buildStockSummary($item)],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStockSummary(Item $item): array
    {
        $balances = $item->stockBalances;

        $perLocation = $balances
            ->groupBy('location_id')
            ->map(function ($locationBalances) {
                $location = $locationBalances->first()->location;

                return [
                    'location_name' => $location?->location_name,
                    'available' => $locationBalances
                        ->where('stock_status', StockStatus::AVAILABLE)
                        ->sum('qty'),
                    'damaged' => $locationBalances
                        ->where('stock_status', StockStatus::DAMAGED)
                        ->sum('qty'),
                    'lost' => $locationBalances
                        ->where('stock_status', StockStatus::LOST)
                        ->sum('qty'),
                ];
            })
            ->values()
            ->all();

        return [
            'total_available' => $balances
                ->where('stock_status', StockStatus::AVAILABLE)
                ->sum('qty'),
            'total_damaged' => $balances
                ->where('stock_status', StockStatus::DAMAGED)
                ->sum('qty'),
            'total_lost' => $balances
                ->where('stock_status', StockStatus::LOST)
                ->sum('qty'),
            'per_location' => $perLocation,
        ];
    }
}
