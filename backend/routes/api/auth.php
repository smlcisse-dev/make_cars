<?php

use App\Http\Controllers\Api\Auth\ExpressClaimController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('register/automobiliste', [RegisterController::class, 'automobiliste']);
Route::post('register/professionnel', [RegisterController::class, 'professional']);
Route::post('login', [SessionController::class, 'store']);
Route::post('login/google', [SessionController::class, 'google']);

/**
 * Réclamation d'un compte "express" par son propriétaire réel (CLAUDE.md §5,
 * ajout v0.17) : envoie un email avec un lien signé permettant de définir un
 * mot de passe.
 */
Route::post('express-claim', [ExpressClaimController::class, 'request']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('logout', [SessionController::class, 'destroy']);
});
