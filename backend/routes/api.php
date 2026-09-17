<?php

use App\Http\Controllers\Api\Public\ExpressClientClaimController;
use App\Http\Controllers\Api\Public\QuoteEmailDecisionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

/**
 * Décision d'un devis par lien signé, pour un client "compte express" sans
 * app (CLAUDE.md §5, ajout v0.9). Publique (pas de Sanctum) : la signature
 * temporaire fait office d'authentification à usage limité.
 */
Route::middleware('signed')->group(function () {
    Route::get('quotes/{quote}/versions/{version}/email-decision/accept', [QuoteEmailDecisionController::class, 'accept'])
        ->name('quotes.email-decision.accept');
    Route::get('quotes/{quote}/versions/{version}/email-decision/reject', [QuoteEmailDecisionController::class, 'reject'])
        ->name('quotes.email-decision.reject');

    /**
     * Réclamation d'un compte "express" par son propriétaire réel
     * (CLAUDE.md §5, ajout v0.17). GET et POST partagent la même URL signée
     * (voir ExpressClientClaimService) : la vérification de signature ne
     * porte que sur l'URL, pas sur la méthode HTTP.
     */
    Route::get('express-clients/{user}/claim', [ExpressClientClaimController::class, 'show'])
        ->name('express-clients.claim.show');
    Route::post('express-clients/{user}/claim', [ExpressClientClaimController::class, 'confirm'])
        ->name('express-clients.claim.confirm');
});

Route::prefix('auth')->group(base_path('routes/api/auth.php'));
Route::prefix('admin')->group(base_path('routes/api/admin.php'));
Route::prefix('garage')->group(base_path('routes/api/garage.php'));
Route::prefix('mobile')->group(base_path('routes/api/mobile.php'));
Route::prefix('market-space')->group(base_path('routes/api/market-space.php'));
