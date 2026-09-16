<?php

use App\Http\Controllers\Api\Garage\GarageImageController;
use App\Http\Controllers\Api\Garage\OpeningHoursController;
use App\Http\Controllers\Api\Garage\ProductController;
use App\Http\Controllers\Api\Garage\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:garagiste'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/opening-hours', [OpeningHoursController::class, 'update']);
    Route::post('profile/images', [GarageImageController::class, 'store']);
    Route::delete('profile/images/{image}', [GarageImageController::class, 'destroy']);

    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::put('products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
});
