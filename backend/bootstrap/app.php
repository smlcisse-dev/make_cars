<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureRegistrationIsApproved;
use App\Http\Middleware\EnsureRegistrationIsEditable;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
