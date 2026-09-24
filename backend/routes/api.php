<?php

use App\Http\Controllers\Api\Public\ExpressClientClaimController;
use App\Http\Controllers\Api\Public\LocationController;
use App\Http\Controllers\Api\Public\QuoteEmailDecisionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
})->middleware('throttle:public');

/**
 * Référentiel du découpage administratif, pour les selects en cascade
 * (CLAUDE.md §5, ajout v0.19).
 */
Route::middleware('throttle:public')->group(function () {
    Route::get('locations/departments', [LocationController::class, 'departments']);
    Route::get('locations/departments/{department}/communes', [LocationController::class, 'communes']);
    Route::get('locations/communes/{commune}/arrondissements', [LocationController::class, 'arrondissements']);
});

/**
 * Liens reçus par email (CLAUDE.md §5, ajouts v0.9, v0.17 et v0.30).
 * Publics (pas de Sanctum) : la signature temporaire fait office
 * d'authentification à usage limité. Signature relative (`signed:relative`,
 * chemin + paramètres) : le frontend appelle l'API par un hôte qui peut
 * différer d'`APP_URL`. Une même URL signée sert au GET et au POST : la
 * vérification ne porte que sur l'URL, jamais sur la méthode HTTP. Un GET
 * n'a jamais d'effet ; seul le POST (action explicite) agit.
 */
Route::middleware(['throttle:email-link', 'signed:relative'])->group(function () {
    // Décision d'un devis par un client "compte express" sans app.
    Route::get('quotes/{quote}/versions/{version}/email-decision', [QuoteEmailDecisionController::class, 'show'])
        ->name('quotes.email-decision');
    Route::post('quotes/{quote}/versions/{version}/email-decision', [QuoteEmailDecisionController::class, 'decide'])
        ->name('quotes.email-decision.decide');

    // Réclamation d'un compte "express" par son propriétaire réel.
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
