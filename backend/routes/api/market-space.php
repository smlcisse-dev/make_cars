<?php

use App\Http\Controllers\Api\MarketSpace\DeviceTokenController;
use App\Http\Controllers\Api\MarketSpace\DisputeController;
use App\Http\Controllers\Api\MarketSpace\MarketSpaceImageController;
use App\Http\Controllers\Api\MarketSpace\NotificationController;
use App\Http\Controllers\Api\MarketSpace\OpeningHoursController;
use App\Http\Controllers\Api\MarketSpace\OrderController;
use App\Http\Controllers\Api\MarketSpace\ProductController;
use App\Http\Controllers\Api\MarketSpace\ProfileController;
use App\Http\Controllers\Api\MarketSpace\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:market_space'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/opening-hours', [OpeningHoursController::class, 'update']);
    Route::post('profile/images', [MarketSpaceImageController::class, 'store']);
    Route::delete('profile/images/{image}', [MarketSpaceImageController::class, 'destroy']);

    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::put('products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid']);
    Route::get('orders/{order}/pdf', [OrderController::class, 'downloadPdf']);

    Route::get('reviews', [ReviewController::class, 'index']);

    Route::get('disputes', [DisputeController::class, 'index']);
    Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('disputes/{dispute}/respond', [DisputeController::class, 'respond']);
    Route::get('disputes/{dispute}/attachments/{attachment}/download', [DisputeController::class, 'downloadAttachment']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::put('device-tokens', [DeviceTokenController::class, 'store']);
});
