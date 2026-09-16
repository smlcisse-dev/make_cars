<?php

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('register/automobiliste', [RegisterController::class, 'automobiliste']);
Route::post('register/professionnel', [RegisterController::class, 'professional']);
Route::post('login', [SessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('logout', [SessionController::class, 'destroy']);
});
