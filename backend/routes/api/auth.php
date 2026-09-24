<?php

use App\Http\Controllers\Api\Auth\ExpressClaimController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\ProfessionalSignupController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('register/automobiliste', [RegisterController::class, 'automobiliste']);

/**
 * Inscription professionnelle courte avec vérification de l'email par code
 * (CLAUDE.md §5, ajout v0.26) — publique, limitée à 10 requêtes/minute par IP
 * pour freiner la force brute sur le code et l'envoi massif d'emails.
 */
Route::middleware('throttle:10,1')->group(function () {
    Route::post('register/professionnel', [ProfessionalSignupController::class, 'store']);
    Route::post('register/professionnel/{uuid}/verify', [ProfessionalSignupController::class, 'verify']);
    Route::post('register/professionnel/{uuid}/resend', [ProfessionalSignupController::class, 'resend']);
});

/**
 * Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29) — publique,
 * même limite que l'inscription pour freiner la force brute sur le code.
 */
Route::middleware('throttle:10,1')->group(function () {
    Route::post('password/forgot', [PasswordResetController::class, 'forgot']);
    Route::post('password/reset', [PasswordResetController::class, 'reset']);
});

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
