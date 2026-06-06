<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    public function grossProfit(Request $request, ReportExportService $exportService, ReportService $reportService)
    {
        $this->ensureOwner($request->user());

        $dateFrom = $request->query('from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('to', now()->toDateString());

        $perItemProfit = $reportService->grossProfitPerItem($dateFrom, $dateTo);
        $expensesByEvent = $reportService->eventExpensesByEvent($dateFrom, $dateTo);
        $totalExpenses = $reportService->totalEventExpenses($dateFrom, $dateTo);
        $summary = $exportService->buildGrossProfitSummary($perItemProfit, $totalExpenses);

        return $exportService->exportGrossProfit(
            $summary,
            $perItemProfit,
            $expensesByEvent,
            $dateFrom,
            $dateTo,
        );
    }

    public function stock(Request $request, ReportExportService $exportService, ReportService $reportService)
    {
        $this->ensureFullReportAccess($request->user());

        return $exportService->exportStock(
            $reportService->stockReportMatrix(),
            $reportService,
        );
    }

    public function sales(Request $request, ReportExportService $exportService, ReportService $reportService)
    {
        $user = $this->ensureFullReportAccess($request->user());

        $dateFrom = $request->query('from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('to', now()->toDateString());

        $salesByLocation = $reportService->salesByLocation($user, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return $exportService->exportSales(
            $salesByLocation,
            (float) $salesByLocation->sum('total_sales'),
            (int) $salesByLocation->sum('transaction_count'),
            $dateFrom,
            $dateTo,
        );
    }

    private function ensureOwner(?User $user): User
    {
        if (! $user || $user->role !== UserRole::OWNER) {
            abort(403);
        }

        return $user;
    }

    private function ensureFullReportAccess(?User $user): User
    {
        if (! $user || ! $user->role->canViewFullReport()) {
            abort(403);
        }

        return $user;
    }
}
