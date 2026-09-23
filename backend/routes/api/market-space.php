<?php

use App\Http\Controllers\Api\MarketSpace\ConversationController;
use App\Http\Controllers\Api\MarketSpace\DeviceTokenController;
use App\Http\Controllers\Api\MarketSpace\DisputeController;
use App\Http\Controllers\Api\MarketSpace\MarketSpaceImageController;
use App\Http\Controllers\Api\MarketSpace\NotificationController;
use App\Http\Controllers\Api\MarketSpace\OpeningHoursController;
use App\Http\Controllers\Api\MarketSpace\OrderController;
use App\Http\Controllers\Api\MarketSpace\ProductController;
use App\Http\Controllers\Api\MarketSpace\ProfileController;
use App\Http\Controllers\Api\MarketSpace\ReviewController;
use App\Http\Controllers\Api\Professional\RegistrationDossierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:market_space'])->group(function () {
    // Accessibles quel que soit le statut du dossier (CLAUDE.md §5, ajout
    // v0.26) : lecture du profil, téléchargement de son propre document,
    // soumission pour validation.
    Route::get('profile', [ProfileController::class, 'show']);
    Route::get('profile/legal/document', [RegistrationDossierController::class, 'downloadDocument']);
    Route::get('profile/legal/identity-document', [RegistrationDossierController::class, 'downloadIdentityDocument']);
    Route::post('profile/submit', [RegistrationDossierController::class, 'submit']);
    Route::post('profile/reactivation-request', [RegistrationDossierController::class, 'requestReactivation']);
    Route::get('profile/reactivation-requests/{reactivationRequest}/attachments/{attachment}', [RegistrationDossierController::class, 'downloadReactivationAttachment'])
        ->name('market-space.reactivation-requests.attachments.download');

    // Écritures du profil : verrouillées pendant l'examen du dossier.
    Route::middleware('registration.editable')->group(function () {
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/opening-hours', [OpeningHoursController::class, 'update']);
        Route::post('profile/images', [MarketSpaceImageController::class, 'store']);
        Route::delete('profile/images/{image}', [MarketSpaceImageController::class, 'destroy']);
        Route::put('profile/legal', [RegistrationDossierController::class, 'updateLegal']);
        Route::post('profile/legal/document', [RegistrationDossierController::class, 'uploadDocument']);
        Route::post('profile/legal/identity-document', [RegistrationDossierController::class, 'uploadIdentityDocument']);
    });

    // Tout le reste de l'espace pro exige un dossier approuvé (CLAUDE.md §5,
    // ajout v0.26) puis un profil complet (ajout v0.20).
    Route::middleware(['registration.approved', 'profile.complete'])->group(function () {
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

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
        Route::get('conversations/{conversation}/messages/{message}/image', [ConversationController::class, 'downloadImage']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::put('device-tokens', [DeviceTokenController::class, 'store']);
    });
});
