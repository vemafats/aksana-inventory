<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function mobileSummary(): JsonResponse
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $netSalesToday = (float) SalesTransaction::query()
            ->whereDate('transaction_date', $today)
            ->sum('grand_total');

        $netSalesYesterday = (float) SalesTransaction::query()
            ->whereDate('transaction_date', $yesterday)
            ->sum('grand_total');

        $changePct = $netSalesYesterday > 0
            ? round((($netSalesToday - $netSalesYesterday) / $netSalesYesterday) * 100, 1)
            : 0.0;

        $itemsSold = (int) SalesItem::query()
            ->whereHas('salesTransaction', fn ($q) => $q->whereDate('transaction_date', $today))
            ->sum('qty');

        $transactionCount = SalesTransaction::query()
            ->whereDate('transaction_date', $today)
            ->count();

        $avgBasket = $transactionCount > 0
            ? $netSalesToday / $transactionCount
            : 0.0;

        $sevenDayTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $sevenDayTrend[] = (float) SalesTransaction::query()
                ->whereDate('transaction_date', $date)
                ->sum('grand_total');
        }

        $topSkuRow = SalesItem::query()
            ->select('items.sku', DB::raw('SUM(sales_items.qty) as qty_sold'))
            ->join('items', 'sales_items.item_id', '=', 'items.id')
            ->whereHas('salesTransaction', fn ($q) => $q->whereDate('transaction_date', $today))
            ->groupBy('items.sku')
            ->orderByDesc('qty_sold')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'net_sales' => $netSalesToday,
                'net_sales_change_pct' => $changePct,
                'items_sold' => $itemsSold,
                'avg_basket' => $avgBasket,
                'seven_day_trend' => $sevenDayTrend,
                'top_sku' => $topSkuRow ? [
                    'sku' => $topSkuRow->sku,
                    'qty_sold' => (int) $topSkuRow->qty_sold,
                ] : null,
            ],
        ]);
    }
}
