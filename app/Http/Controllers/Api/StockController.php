<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationType;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\StockBalance;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function warehouse(): JsonResponse
    {
        $warehouse = Location::query()
            ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->firstOrFail();

        $balances = StockBalance::query()
            ->where('location_id', $warehouse->id)
            ->with([
                'item.category',
                'item.brand',
                'item.color',
                'item.size',
            ])
            ->get()
            ->groupBy('item_id')
            ->map(function ($itemBalances) {
                $item = $itemBalances->first()->item;

                return [
                    'item' => $item,
                    'available' => $itemBalances
                        ->where('stock_status', StockStatus::AVAILABLE)
                        ->sum('qty'),
                    'damaged' => $itemBalances
                        ->where('stock_status', StockStatus::DAMAGED)
                        ->sum('qty'),
                    'lost' => $itemBalances
                        ->where('stock_status', StockStatus::LOST)
                        ->sum('qty'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }
}
