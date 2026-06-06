<?php

use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/admin/reports/gross-profit/export', [ReportExportController::class, 'grossProfit'])
        ->name('reports.gross-profit.export');
    Route::get('/admin/reports/stock/export', [ReportExportController::class, 'stock'])
        ->name('reports.stock.export');
    Route::get('/admin/reports/sales/export', [ReportExportController::class, 'sales'])
        ->name('reports.sales.export');
});
