<?php

use App\Http\Controllers\Api\MarketSpace\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:market_space'])->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::put('products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
});
