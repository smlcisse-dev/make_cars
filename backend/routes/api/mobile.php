<?php

use App\Http\Controllers\Api\Mobile\GarageController;
use Illuminate\Support\Facades\Route;

Route::get('garages', [GarageController::class, 'index']);
Route::get('garages/{garage}', [GarageController::class, 'show']);
