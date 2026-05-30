<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockIn\StoreStockInRequest;
use App\Models\StockInItem;
use App\Models\StockInTransaction;
use App\Models\StockMovement;
use App\Services\StockInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockInController extends Controller
{
    public function __construct(
        private readonly StockInService $stockInService,
    ) {}

    public function store(StoreStockInRequest $request): JsonResponse
    {
        try {
            $transaction = $this->stockInService->createTransaction(
                $request->validated(),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Barang masuk berhasil dicatat',
            'data' => $this->formatTransaction($transaction),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockInTransaction::query()
            ->with([
                'stockInItems.item',
                'createdBy',
            ])
            ->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date('date_to'));
        }

        $transactions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions->through(fn (StockInTransaction $transaction) => $this->formatTransaction($transaction)),
        ]);
    }

    public function show(StockInTransaction $transaction): JsonResponse
    {
        $transaction->load([
            'stockInItems.item.category',
            'stockInItems.item.brand',
            'stockInItems.item.color',
            'stockInItems.item.size',
            'createdBy',
        ]);

        $movements = StockMovement::query()
            ->where('reference_type', 'stock_in')
            ->where('reference_id', $transaction->id)
            ->with('item')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->formatTransaction($transaction),
                ['stock_movements' => $movements],
            ),
        ]);
    }

    public function updateItemPrice(
        Request $request,
        StockInTransaction $stockIn,
        StockInItem $item,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, [UserRole::OWNER, UserRole::ADMIN], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'supplier_cost' => ['required', 'numeric', 'min:0'],
            'margin_type' => ['required', 'in:nominal,percentage,none'],
            'margin_value' => ['required', 'numeric', 'min:0'],
            'qc_note' => ['nullable', 'string'],
            'photo_id' => ['nullable', 'uuid', 'exists:photos,id'],
        ]);

        try {
            $updatedItem = $this->stockInService->updateItemPrice(
                $stockIn,
                $item,
                (float) $validated['supplier_cost'],
                $validated['margin_type'],
                (float) $validated['margin_value'],
                $validated['qc_note'] ?? null,
                $validated['photo_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Harga berhasil diperbarui',
            'data' => [
                'item_name' => $updatedItem->item?->item_name,
                'base_selling_price' => (float) $updatedItem->base_selling_price,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransaction(StockInTransaction $transaction): array
    {
        $data = $transaction->toArray();

        if ($transaction->relationLoaded('stockInItems')) {
            $data['items'] = $transaction->stockInItems->map(function ($stockInItem) {
                $item = $stockInItem->toArray();

                if ($stockInItem->relationLoaded('item') && $stockInItem->item) {
                    $item['item'] = $stockInItem->item->toArray();
                }

                return $item;
            })->all();
        }

        if ($transaction->relationLoaded('createdBy') && $transaction->createdBy) {
            $data['created_by_user'] = [
                'id' => $transaction->createdBy->id,
                'name' => $transaction->createdBy->name,
                'email' => $transaction->createdBy->email,
            ];
        }

        return $data;
    }
}
