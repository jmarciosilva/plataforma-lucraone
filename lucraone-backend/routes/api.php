<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('auth.logout');
});
