<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CatalogQrCodeController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockInController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);

    Route::get('/catalogs/by-barcode/{barcode}', [CatalogController::class, 'findByBarcode']);
    Route::post('/catalogs/print-labels', [CatalogQrCodeController::class, 'printLabel']);
    Route::get('/catalogs/{item}/qrcode', [CatalogQrCodeController::class, 'show']);
    Route::apiResource('catalogs', CatalogController::class)->only(['index', 'show', 'store', 'update']);

    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos/{photo}', [PhotoController::class, 'show']);

    Route::get('/stocks', [StockController::class, 'index']);
    Route::get('/stocks/warehouse', [StockController::class, 'warehouse']);
    Route::get('/stocks/location/{locationId}', [StockController::class, 'locationStock']);
    Route::get('/stocks/item/{itemId}', [StockController::class, 'itemStock']);

    Route::post('/stock-in', [StockInController::class, 'store']);
    Route::get('/stock-in', [StockInController::class, 'index']);
    Route::get('/stock-in/{transaction}', [StockInController::class, 'show']);
    Route::put('/stock-in/{stockIn}/items/{item}', [StockInController::class, 'updateItemPrice']);

    Route::post('/transfers', [TransferController::class, 'store']);
    Route::post('/returns', [TransferController::class, 'storeReturn']);
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::get('/transfers/{transfer}', [TransferController::class, 'show']);

    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/my-active', [EventController::class, 'myActive']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::post('/events/{event}/end', [EventController::class, 'end']);

    Route::get('/stock-opnames/active', [StockOpnameController::class, 'getActive']);
    Route::post('/stock-opnames', [StockOpnameController::class, 'store']);
    Route::get('/stock-opnames', [StockOpnameController::class, 'index']);
    Route::get('/stock-opnames/{opname}', [StockOpnameController::class, 'show']);
    Route::post('/stock-opnames/{opname}/items', [StockOpnameController::class, 'addItem']);
    Route::post('/stock-opnames/{opname}/submit', [StockOpnameController::class, 'submit']);
    Route::post('/stock-opnames/{opname}/validate', [StockOpnameController::class, 'validate']);
    Route::post('/stock-opnames/{opname}/reject', [StockOpnameController::class, 'reject']);

    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/sales', [LocationController::class, 'salesLocations']);
    Route::get('/locations/central-warehouse', [LocationController::class, 'centralWarehouse']);
    Route::post('/locations/{location}/close', [LocationController::class, 'close']);

    Route::get('/employees', [EmployeeController::class, 'index']);

    Route::prefix('reports')->group(function (): void {
        Route::get('dashboard-summary', [ReportController::class, 'dashboardSummary']);
        Route::get('warehouse-stock', [ReportController::class, 'warehouseStock']);
        Route::get('location-stock', [ReportController::class, 'locationStock']);
        Route::get('total-capital', [ReportController::class, 'totalCapital']);
        Route::get('low-stock-items', [ReportController::class, 'lowStockItems']);
        Route::get('slow-moving-items', [ReportController::class, 'slowMovingItems']);
        Route::get('gross-profit', [ReportController::class, 'grossProfit']);
        Route::get('best-selling-products', [ReportController::class, 'bestSellingProducts']);
        Route::get('sales-by-location', [ReportController::class, 'salesByLocation']);
        Route::get('sales-by-employee', [ReportController::class, 'salesByEmployee']);
        Route::get('mobile-summary', [ReportController::class, 'mobileSummary']);
    });

    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/sales/{transaction}', [SalesController::class, 'show']);
});
