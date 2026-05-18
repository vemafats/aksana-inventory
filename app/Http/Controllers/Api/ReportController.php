<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\PasswordVerificationService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function grossProfit(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->grossProfit(
                $request->user(),
                $request->only(['date_from', 'date_to', 'location_id']),
            ),
        ]);
    }

    public function bestSellingProducts(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->bestSellingProducts(
                $request->user(),
                $request->only(['date_from', 'date_to', 'limit']),
            ),
        ]);
    }

    public function salesByLocation(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->salesByLocation(
                $request->user(),
                $request->only(['date_from', 'date_to']),
            ),
        ]);
    }

    public function salesByEmployee(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->salesByEmployee(
                $request->user(),
                $request->only(['date_from', 'date_to', 'location_id']),
            ),
        ]);
    }

    public function mobileSummary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->mobileSummary($request->user()),
        ]);
    }
}
