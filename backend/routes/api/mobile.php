<?php

use App\Http\Controllers\Api\Mobile\AppointmentController;
use App\Http\Controllers\Api\Mobile\ConversationController;
use App\Http\Controllers\Api\Mobile\GarageController;
use App\Http\Controllers\Api\Mobile\MarketSpaceController;
use App\Http\Controllers\Api\Mobile\QuoteController;
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

    Route::get('appointments/{appointment}/quote', [QuoteController::class, 'show']);
    Route::post('quotes/{quote}/versions/{version}/accept', [QuoteController::class, 'accept']);
    Route::post('quotes/{quote}/versions/{version}/reject', [QuoteController::class, 'reject']);
    Route::get('quotes/{quote}/versions/{version}/pdf', [QuoteController::class, 'downloadPdf']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::get('conversations/{conversation}/messages/{message}/image', [ConversationController::class, 'downloadImage']);
});
