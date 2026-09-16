<?php

use App\Http\Controllers\Api\Mobile\GarageController;
use App\Http\Controllers\Api\Mobile\MarketSpaceController;
use Illuminate\Support\Facades\Route;

Route::get('garages', [GarageController::class, 'index']);
Route::get('garages/{garage}', [GarageController::class, 'show']);

Route::get('market-space-accounts', [MarketSpaceController::class, 'index']);
Route::get('market-space-accounts/{marketSpaceAccount}', [MarketSpaceController::class, 'show']);
