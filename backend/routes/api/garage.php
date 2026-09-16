<?php

use App\Http\Controllers\Api\Garage\GarageImageController;
use App\Http\Controllers\Api\Garage\OpeningHoursController;
use App\Http\Controllers\Api\Garage\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:garagiste'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/opening-hours', [OpeningHoursController::class, 'update']);
    Route::post('profile/images', [GarageImageController::class, 'store']);
    Route::delete('profile/images/{image}', [GarageImageController::class, 'destroy']);
});
