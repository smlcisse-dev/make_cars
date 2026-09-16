<?php

use App\Http\Controllers\Api\Mobile\AppointmentController;
use App\Http\Controllers\Api\Mobile\GarageController;
use App\Http\Controllers\Api\Mobile\MarketSpaceController;
use Illuminate\Support\Facades\Route;

Route::get('garages', [GarageController::class, 'index']);
Route::get('garages/{garage}', [GarageController::class, 'show']);

Route::get('market-space-accounts', [MarketSpaceController::class, 'index']);
Route::get('market-space-accounts/{marketSpaceAccount}', [MarketSpaceController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:automobiliste'])->group(function () {
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/accept-reschedule', [AppointmentController::class, 'acceptReschedule']);
});
