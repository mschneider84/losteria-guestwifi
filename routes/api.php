<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Middleware\ApiKeyAuth;
use Illuminate\Support\Facades\Route;

// Schnittstelle für das L'Osteria Dashboard (Spezifikation: docs/dashboard-schnittstelle.md)
Route::prefix('v1')->group(function () {
    Route::middleware(ApiKeyAuth::class)->group(function () {
        Route::get('/status', [DashboardApiController::class, 'status']);
        Route::get('/standorte', [DashboardApiController::class, 'locations']);
        Route::get('/auswertung', [DashboardApiController::class, 'stats']);
        Route::get('/aktionen', [DashboardApiController::class, 'promotions']);
    });
    Route::middleware(ApiKeyAuth::class.':write')->group(function () {
        Route::put('/aktionen/{id}', [DashboardApiController::class, 'upsertPromotion']);
        Route::delete('/aktionen/{id}', [DashboardApiController::class, 'deletePromotion']);
    });
});
