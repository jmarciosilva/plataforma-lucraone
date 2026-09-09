<?php

use App\Modules\Automation\Http\Controllers\AutomationLogController;
use App\Modules\Automation\Http\Controllers\AutomationRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1')->group(function () {
    Route::get('automation-rules', [AutomationRuleController::class, 'index']);
    Route::post('automation-rules', [AutomationRuleController::class, 'store']);
    Route::get('automation-rules/{id}', [AutomationRuleController::class, 'show']);
    Route::put('automation-rules/{id}', [AutomationRuleController::class, 'update']);
    Route::delete('automation-rules/{id}', [AutomationRuleController::class, 'destroy']);

    Route::get('automation-logs', [AutomationLogController::class, 'index']);
});
