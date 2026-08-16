<?php

use App\Modules\Products\Http\Controllers\CategoryController;
use App\Modules\Products\Http\Controllers\PriceController;
use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Products
    Route::get('products/search/{query}', [ProductController::class, 'search']);
    Route::get('products/status/{status}', [ProductController::class, 'byStatus']);
    Route::apiResource('products', ProductController::class);

    // Categories
    Route::get('categories/roots', [CategoryController::class, 'roots']);
    Route::get('categories/{id}/children', [CategoryController::class, 'children']);
    Route::apiResource('categories', CategoryController::class);

    // Prices
    Route::get('prices/product/{productId}', [PriceController::class, 'byProduct']);
    Route::get('prices/history/{productId}', [PriceController::class, 'history']);
    Route::get('prices/sale/{productId}', [PriceController::class, 'salePrice']);
    Route::get('prices/cost/{productId}', [PriceController::class, 'costPrice']);
    Route::post('prices', [PriceController::class, 'store']);
    Route::delete('prices/{priceId}', [PriceController::class, 'destroy']);
});
