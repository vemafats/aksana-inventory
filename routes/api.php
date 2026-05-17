<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CatalogQrCodeController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockInController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);

    Route::get('/catalogs/by-barcode/{barcode}', [CatalogController::class, 'findByBarcode']);
    Route::post('/catalogs/print-labels', [CatalogQrCodeController::class, 'printLabel']);
    Route::get('/catalogs/{item}/qrcode', [CatalogQrCodeController::class, 'show']);
    Route::apiResource('catalogs', CatalogController::class)->only(['index', 'show', 'store', 'update']);

    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos/{photo}', [PhotoController::class, 'show']);

    Route::get('/stocks/warehouse', [StockController::class, 'warehouse']);

    Route::post('/stock-in', [StockInController::class, 'store']);
    Route::get('/stock-in', [StockInController::class, 'index']);
    Route::get('/stock-in/{transaction}', [StockInController::class, 'show']);

    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::get('/transfers/{transfer}', [TransferController::class, 'show']);
});
