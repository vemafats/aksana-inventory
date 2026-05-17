<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/catalogs/by-barcode/{barcode}', [CatalogController::class, 'findByBarcode']);
    Route::apiResource('catalogs', CatalogController::class)->only(['index', 'show', 'store', 'update']);
});
