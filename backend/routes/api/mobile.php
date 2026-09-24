<?php

use App\Http\Controllers\Api\Mobile\AppointmentController;
use App\Http\Controllers\Api\Mobile\ConversationController;
use App\Http\Controllers\Api\Mobile\DeviceTokenController;
use App\Http\Controllers\Api\Mobile\DisputeController;
use App\Http\Controllers\Api\Mobile\GarageController;
use App\Http\Controllers\Api\Mobile\MarketSpaceController;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Http\Controllers\Api\Mobile\OrderController;
use App\Http\Controllers\Api\Mobile\QuoteController;
use App\Http\Controllers\Api\Mobile\ReviewController;
use App\Http\Controllers\Api\Mobile\SearchController;
use Illuminate\Support\Facades\Route;

// Lectures publiques (sans session) : limite `public` (CLAUDE.md §4).
Route::middleware('throttle:public')->group(function () {
    Route::get('garages', [GarageController::class, 'index']);
    Route::get('garages/{garage}', [GarageController::class, 'show']);
    Route::get('garages/{garage}/reviews', [ReviewController::class, 'garageReviews']);

    Route::get('market-space-accounts', [MarketSpaceController::class, 'index']);
    Route::get('market-space-accounts/{marketSpaceAccount}', [MarketSpaceController::class, 'show']);
    Route::get('market-space-accounts/{marketSpaceAccount}/reviews', [ReviewController::class, 'marketSpaceReviews']);

    Route::get('search/nearby', [SearchController::class, 'nearby']);
});

Route::middleware(['auth:sanctum', 'role:automobiliste'])->group(function () {
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/accept-reschedule', [AppointmentController::class, 'acceptReschedule']);

    Route::get('quotes', [QuoteController::class, 'index']);
    Route::get('quotes/{quote}', [QuoteController::class, 'show']);
    Route::get('appointments/{appointment}/quote', [QuoteController::class, 'showForAppointment']);
    Route::post('quotes/{quote}/versions/{version}/accept', [QuoteController::class, 'accept']);
    Route::post('quotes/{quote}/versions/{version}/reject', [QuoteController::class, 'reject']);
    Route::get('quotes/{quote}/versions/{version}/pdf', [QuoteController::class, 'downloadPdf']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('orders/{order}/pdf', [OrderController::class, 'downloadPdf']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::get('conversations/{conversation}/messages/{message}/image', [ConversationController::class, 'downloadImage']);

    Route::get('reviews', [ReviewController::class, 'index']);
    Route::post('quotes/{quote}/review', [ReviewController::class, 'storeForQuote']);
    Route::post('orders/{order}/review', [ReviewController::class, 'storeForOrder']);

    Route::get('disputes', [DisputeController::class, 'index']);
    Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('quotes/{quote}/dispute', [DisputeController::class, 'storeForQuote']);
    Route::post('orders/{order}/dispute', [DisputeController::class, 'storeForOrder']);
    Route::get('disputes/{dispute}/attachments/{attachment}/download', [DisputeController::class, 'downloadAttachment']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::put('device-tokens', [DeviceTokenController::class, 'store']);
});
