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
