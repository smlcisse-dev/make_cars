<?php

use App\Http\Controllers\Api\Garage\AppointmentController;
use App\Http\Controllers\Api\Garage\ConversationController;
use App\Http\Controllers\Api\Garage\GarageImageController;
use App\Http\Controllers\Api\Garage\OpeningHoursController;
use App\Http\Controllers\Api\Garage\ProductController;
use App\Http\Controllers\Api\Garage\ProfileController;
use App\Http\Controllers\Api\Garage\QuoteController;
use App\Http\Controllers\Api\Garage\ServiceController;
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

    Route::get('services', [ServiceController::class, 'index']);
    Route::post('services', [ServiceController::class, 'store']);
    Route::put('services/{service}', [ServiceController::class, 'update']);
    Route::put('services/{service}/availability', [ServiceController::class, 'updateAvailability']);
    Route::delete('services/{service}', [ServiceController::class, 'destroy']);

    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('appointments/{appointment}/reject', [AppointmentController::class, 'reject']);
    Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);

    Route::post('appointments/{appointment}/quote', [QuoteController::class, 'store']);
    Route::get('appointments/{appointment}/quote', [QuoteController::class, 'show']);
    Route::put('quotes/{quote}/versions/{version}', [QuoteController::class, 'updateVersion']);
    Route::post('quotes/{quote}/versions/{version}/send', [QuoteController::class, 'send']);
    Route::get('quotes/{quote}/versions/{version}/pdf', [QuoteController::class, 'downloadPdf']);
    Route::post('quotes/{quote}/versions', [QuoteController::class, 'storeNextVersion']);
    Route::post('quotes/{quote}/start', [QuoteController::class, 'start']);
    Route::post('quotes/{quote}/mark-paid', [QuoteController::class, 'markPaid']);
    Route::post('quotes/{quote}/abandon', [QuoteController::class, 'abandon']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::get('conversations/{conversation}/messages/{message}/image', [ConversationController::class, 'downloadImage']);
});
