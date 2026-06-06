<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    public function grossProfit(Request $request, ReportExportService $exportService, ReportService $reportService)
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::OWNER) {
            abort(403);
        }

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
}
