<?php

use App\Modules\Reporting\Http\Controllers\AnalyticsController;
use App\Modules\Reporting\Http\Controllers\DashboardController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1')->group(function () {
    Route::get('reports/sales', [ReportController::class, 'sales']);
    Route::get('reports/inventory', [ReportController::class, 'inventory']);
    Route::get('reports/customers', [ReportController::class, 'customers']);

    Route::get('dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('analytics/trends', [AnalyticsController::class, 'trends']);
});
