<?php

use App\Http\Controllers\Api\Admin\AppointmentController;
use App\Http\Controllers\Api\Admin\ConversationController;
use App\Http\Controllers\Api\Admin\DisputeController;
use App\Http\Controllers\Api\Admin\GarageController;
use App\Http\Controllers\Api\Admin\MarketSpaceController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\QuoteController;
use App\Http\Controllers\Api\Admin\RegistrationController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('registrations', [RegistrationController::class, 'index']);
    Route::get('registrations/{registration}', [RegistrationController::class, 'show']);
    Route::post('registrations/{registration}/approve', [RegistrationController::class, 'approve']);
    Route::post('registrations/{registration}/reject', [RegistrationController::class, 'reject']);
    Route::post('registrations/{registration}/suspend', [RegistrationController::class, 'suspend']);
    Route::post('registrations/{registration}/reactivate', [RegistrationController::class, 'reactivate']);
    Route::get('registrations/{registration}/documents/{document}', [RegistrationController::class, 'downloadDocument'])
        ->name('admin.registrations.documents.download');

    Route::get('garages', [GarageController::class, 'index']);
    Route::get('garages/{garage}', [GarageController::class, 'show']);

    Route::get('market-space-accounts', [MarketSpaceController::class, 'index']);
    Route::get('market-space-accounts/{marketSpaceAccount}', [MarketSpaceController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products/{product}/approve', [ProductController::class, 'approve']);
    Route::post('products/{product}/reject', [ProductController::class, 'reject']);

    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{service}', [ServiceController::class, 'show']);
    Route::post('services/{service}/approve', [ServiceController::class, 'approve']);
    Route::post('services/{service}/reject', [ServiceController::class, 'reject']);

    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);

    Route::get('quotes', [QuoteController::class, 'index']);
    Route::get('quotes/{quote}', [QuoteController::class, 'show']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);

    Route::get('reviews', [ReviewController::class, 'index']);
    Route::get('reviews/{review}', [ReviewController::class, 'show']);
    Route::post('reviews/{review}/moderate', [ReviewController::class, 'moderate']);

    Route::get('disputes', [DisputeController::class, 'index']);
    Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('disputes/{dispute}/request-response', [DisputeController::class, 'requestResponse']);
    Route::post('disputes/{dispute}/reject', [DisputeController::class, 'reject']);
    Route::post('disputes/{dispute}/resolve', [DisputeController::class, 'resolve']);
    Route::post('disputes/{dispute}/close', [DisputeController::class, 'close']);
    Route::get('disputes/{dispute}/attachments/{attachment}/download', [DisputeController::class, 'downloadAttachment']);

    /**
     * Statistiques agrégées pour les autorités béninoises (CLAUDE.md §1,
     * ajout v0.17) : structures par type/statut, répartition géographique,
     * volume d'activité sur une période optionnelle, avis et réclamations.
     */
    Route::get('statistics', [StatisticsController::class, 'index']);
});
