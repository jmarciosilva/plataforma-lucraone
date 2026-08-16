<?php

use App\Modules\Audit\Http\Controllers\HealthController;
use App\Modules\Identity\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check'])->name('health.check');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('auth.logout');
});

// Products Module Routes
require __DIR__ . '/../app/Modules/Products/Routes/api.php';

// Inventory Module Routes
require __DIR__ . '/../app/Modules/Inventory/Routes/api.php';

// Sales Module Routes
require __DIR__ . '/../app/Modules/Sales/Routes/api.php';

// Reporting Module Routes
require __DIR__ . '/../app/Modules/Reporting/Routes/api.php';
