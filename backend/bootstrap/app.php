<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureRegistrationIsApproved;
use App\Http\Middleware\EnsureRegistrationIsEditable;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'profile.complete' => EnsureProfileIsComplete::class,
            'registration.approved' => EnsureRegistrationIsApproved::class,
            'registration.editable' => EnsureRegistrationIsEditable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Une ApiException est un refus métier normal (code faux, code
        // expiré, demande déjà en attente…), pas une erreur du serveur :
        // elle est rendue au client mais jamais écrite dans le journal.
        $exceptions->dontReport(ApiException::class);

        // Limite de débit dépassée (limites définies dans
        // AppServiceProvider::configureRateLimiting) : message en français et
        // délai d'attente en secondes, que le frontend affiche tel quel.
        $exceptions->render(function (ThrottleRequestsException $exception) {
            $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 60);

            return response()->json([
                'message' => "Trop de tentatives. Réessayez dans {$retryAfter} secondes.",
                'code' => 'too_many_attempts',
                'retry_after' => $retryAfter,
            ], 429, $exception->getHeaders());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
