<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\StoreTransferRequest;
use App\Models\TransferTransaction;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function store(StoreTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->transferService->createTransfer(
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
            'message' => 'Transfer stok berhasil',
            'data' => $this->formatTransfer($transfer),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TransferTransaction::query()
            ->with(['fromLocation', 'toLocation', 'createdBy'])
            ->latest();

        if ($request->filled('from_location_id')) {
            $query->where('from_location_id', $request->string('from_location_id'));
        }

        if ($request->filled('to_location_id')) {
            $query->where('to_location_id', $request->string('to_location_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $transfers = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transfers->through(fn (TransferTransaction $transfer) => $this->formatTransfer($transfer)),
        ]);
    }

    public function show(TransferTransaction $transfer): JsonResponse
    {
        $transfer->load([
            'transferItems.item.category',
            'transferItems.item.brand',
            'transferItems.item.color',
            'transferItems.item.size',
            'fromLocation',
            'toLocation',
            'createdBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTransfer($transfer),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransfer(TransferTransaction $transfer): array
    {
        $data = $transfer->toArray();

        if ($transfer->relationLoaded('transferItems')) {
            $data['items'] = $transfer->transferItems->map(function ($transferItem) {
                $item = $transferItem->toArray();

                if ($transferItem->relationLoaded('item') && $transferItem->item) {
                    $item['item'] = $transferItem->item->toArray();
                }

                return $item;
            })->all();
        }

        if ($transfer->relationLoaded('createdBy') && $transfer->createdBy) {
            $data['created_by_user'] = [
                'id' => $transfer->createdBy->id,
                'name' => $transfer->createdBy->name,
                'email' => $transfer->createdBy->email,
            ];
        }

        return $data;
    }
}
