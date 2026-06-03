<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesRequest;
use App\Models\SalesTransaction;
use App\Services\SalesService;
use App\Support\TimezoneQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SalesController extends Controller
{
    public function __construct(
        private readonly SalesService $salesService,
    ) {}

    public function store(StoreSalesRequest $request): JsonResponse
    {
        try {
            $transaction = $this->salesService->createTransaction(
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
            'message' => 'Transaksi penjualan berhasil',
            'data' => $this->formatTransaction($transaction),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = SalesTransaction::query()
            ->with(['location', 'salesUser', 'createdBy'])
            ->orderByDesc('transaction_date');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->string('location_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->string('employee_id'));
        }

        if ($request->filled('date_from')) {
            TimezoneQuery::whereTimestampFrom(
                $query,
                'transaction_date',
                $request->date('date_from')->toDateString(),
            );
        }

        if ($request->filled('date_to')) {
            TimezoneQuery::whereTimestampTo(
                $query,
                'transaction_date',
                $request->date('date_to')->toDateString(),
            );
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

        $transactions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions->through(fn (SalesTransaction $transaction) => $this->formatTransaction($transaction)),
        ]);
    }

    public function show(SalesTransaction $transaction): JsonResponse
    {
        $transaction->load([
            'salesItems.item.category',
            'salesItems.item.brand',
            'salesItems.item.color',
            'salesItems.item.size',
            'location',
            'salesUser',
            'createdBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransaction(SalesTransaction $transaction): array
    {
        $data = $transaction->toArray();

        if ($transaction->relationLoaded('salesItems')) {
            $data['items'] = $transaction->salesItems->map(function ($salesItem) {
                $row = $salesItem->toArray();

                if ($salesItem->relationLoaded('item') && $salesItem->item) {
                    $row['item'] = $salesItem->item->toArray();
                }

                return $row;
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
