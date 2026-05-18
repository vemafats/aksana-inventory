<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Services\PasswordVerificationService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly PasswordVerificationService $passwordVerificationService,
    ) {}

    public function dashboardSummary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->dashboardSummary($request->user()),
        ]);
    }

    public function warehouseStock(Request $request): JsonResponse
    {
        $items = $this->reportService->warehouseStock($request->only([
            'category_id',
            'brand_id',
            'search',
        ]));

        $page = $items->forPage(
            max(1, (int) $request->integer('page', 1)),
            20,
        );

        $paginator = new LengthAwarePaginator(
            $page->values(),
            $items->count(),
            20,
            max(1, (int) $request->integer('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    public function locationStock(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
        ]);

        try {
            $items = $this->reportService->locationStock(
                $request->string('location_id')->toString(),
                $request->user(),
            );
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

    public function totalCapital(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->header('X-Cost-View-Token');

        if ($user->role !== UserRole::OWNER
          || ! $this->passwordVerificationService->validateCostViewToken($token, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Verifikasi password diperlukan',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->reportService->totalCapital(),
        ]);
    }

    public function lowStockItems(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->lowStockItems($request->user()),
        ]);
    }

    public function slowMovingItems(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 60);

        return response()->json([
            'success' => true,
            'data' => $this->reportService->slowMovingItems($days > 0 ? $days : 60),
        ]);
    }

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
