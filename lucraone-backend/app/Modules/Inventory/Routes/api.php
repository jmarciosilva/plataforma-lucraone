<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\StockLevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1')->group(function () {
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('inventory/overstock', [InventoryController::class, 'overstock']);
    Route::post('inventory/{product_id}/adjust', [InventoryController::class, 'adjust']);
    Route::get('inventory/{product_id}/movements', [InventoryController::class, 'movements']);

    Route::post('stock-levels/{product_id}', [StockLevelController::class, 'store']);
});
