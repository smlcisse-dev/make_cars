<?php

use App\Http\Controllers\Api\Garage\AppointmentController;
use App\Http\Controllers\Api\Garage\ClientController;
use App\Http\Controllers\Api\Garage\ConversationController;
use App\Http\Controllers\Api\Garage\DeviceTokenController;
use App\Http\Controllers\Api\Garage\DisputeController;
use App\Http\Controllers\Api\Garage\GarageImageController;
use App\Http\Controllers\Api\Garage\NotificationController;
use App\Http\Controllers\Api\Garage\OpeningHoursController;
use App\Http\Controllers\Api\Garage\OrderController;
use App\Http\Controllers\Api\Garage\ProductController;
use App\Http\Controllers\Api\Garage\ProfileController;
use App\Http\Controllers\Api\Garage\QuoteController;
use App\Http\Controllers\Api\Garage\ReviewController;
use App\Http\Controllers\Api\Garage\ServiceController;
use App\Http\Controllers\Api\Professional\RegistrationDossierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:garagiste'])->group(function () {
    // Accessibles quel que soit le statut du dossier (CLAUDE.md §5, ajout
    // v0.26) : lecture du profil, téléchargement de son propre document,
    // soumission pour validation.
    Route::get('profile', [ProfileController::class, 'show']);
    Route::get('profile/legal/document', [RegistrationDossierController::class, 'downloadDocument']);
    Route::post('profile/submit', [RegistrationDossierController::class, 'submit']);

    // Écritures du profil : verrouillées pendant l'examen du dossier.
    Route::middleware('registration.editable')->group(function () {
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/opening-hours', [OpeningHoursController::class, 'update']);
        Route::post('profile/images', [GarageImageController::class, 'store']);
        Route::delete('profile/images/{image}', [GarageImageController::class, 'destroy']);
        Route::put('profile/legal', [RegistrationDossierController::class, 'updateLegal']);
        Route::post('profile/legal/document', [RegistrationDossierController::class, 'uploadDocument']);
    });

    // Tout le reste de l'espace pro exige un dossier approuvé (CLAUDE.md §5,
    // ajout v0.26) puis un profil complet (ajout v0.20).
    Route::middleware(['registration.approved', 'profile.complete'])->group(function () {
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

        Route::post('appointments/{appointment}/quote', [QuoteController::class, 'storeForAppointment']);
        Route::get('appointments/{appointment}/quote', [QuoteController::class, 'showForAppointment']);
        Route::get('quotes', [QuoteController::class, 'index']);
        Route::post('quotes', [QuoteController::class, 'store']);
        Route::get('quotes/{quote}', [QuoteController::class, 'show']);
        Route::put('quotes/{quote}/versions/{version}', [QuoteController::class, 'updateVersion']);
        Route::post('quotes/{quote}/versions/{version}/send', [QuoteController::class, 'send']);
        Route::get('quotes/{quote}/versions/{version}/pdf', [QuoteController::class, 'downloadPdf']);
        Route::post('quotes/{quote}/versions', [QuoteController::class, 'storeNextVersion']);
        Route::post('quotes/{quote}/start', [QuoteController::class, 'start']);
        Route::post('quotes/{quote}/mark-paid', [QuoteController::class, 'markPaid']);
        Route::post('quotes/{quote}/abandon', [QuoteController::class, 'abandon']);

        Route::post('clients/express', [ClientController::class, 'storeExpress']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid']);
        Route::get('orders/{order}/pdf', [OrderController::class, 'downloadPdf']);

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
        Route::get('conversations/{conversation}/messages/{message}/image', [ConversationController::class, 'downloadImage']);

        Route::get('reviews', [ReviewController::class, 'index']);

        Route::get('disputes', [DisputeController::class, 'index']);
        Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
        Route::post('disputes/{dispute}/respond', [DisputeController::class, 'respond']);
        Route::get('disputes/{dispute}/attachments/{attachment}/download', [DisputeController::class, 'downloadAttachment']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::put('device-tokens', [DeviceTokenController::class, 'store']);
    });
});
