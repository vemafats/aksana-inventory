<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockBalance;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = StockBalance::query()
            ->select(['id', 'item_id', 'location_id', 'stock_status', 'qty'])
            ->with($this->stockBalanceRelations());

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->string('location_id'));
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->string('item_id'));
        }

        if ($request->filled('stock_status')) {
            $query->where('stock_status', $request->string('stock_status'));
        }

        $balances = $query->orderBy('item_id')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $balances->through(fn (StockBalance $balance) => $this->formatBalance($balance)),
        ]);
    }

    public function warehouse(Request $request): JsonResponse
    {
        $grouped = $this->reportService->warehouseStock($request->only([
            'category_id',
            'brand_id',
            'search',
        ]));

        $items = Item::query()
            ->whereIn('id', $grouped->pluck('item_id'))
            ->get()
            ->keyBy('id');

        $data = $grouped->map(function (array $row) use ($items) {
            $item = $items->get($row['item_id']);

            return [
                'item' => $item,
                'available' => $row['available'],
                'damaged' => $row['damaged'],
                'lost' => $row['lost'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function locationStock(Request $request, string $locationId): JsonResponse
    {
        try {
            $items = $this->reportService->locationStock($locationId, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function itemStock(Request $request, string $itemId): JsonResponse
    {
        $balances = StockBalance::query()
            ->select(['id', 'item_id', 'location_id', 'stock_status', 'qty'])
            ->where('item_id', $itemId)
            ->with($this->stockBalanceRelations())
            ->orderBy('location_id')
            ->get()
            ->map(fn (StockBalance $balance) => $this->formatBalance($balance));

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBalance(StockBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'item_id' => $balance->item_id,
            'location_id' => $balance->location_id,
            'stock_status' => $balance->stock_status instanceof StockStatus
                ? $balance->stock_status->value
                : $balance->stock_status,
            'qty' => $balance->qty,
            'item' => $balance->relationLoaded('item') && $balance->item
                ? $balance->item->toArray()
                : null,
            'location' => $balance->relationLoaded('location') && $balance->location
                ? $balance->location->only([
                    'id',
                    'location_code',
                    'location_name',
                    'location_type',
                    'status',
                ])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockBalanceRelations(): array
    {
        return [
            'item' => fn ($query) => $query->select([
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
            ]),
            'item.category:id,name,code',
            'item.brand:id,name',
            'item.color:id,name,code',
            'item.size:id,name,category_id',
            'item.size.category:id,name',
            'location:id,location_code,location_name,location_type,status',
        ];
    }
}
