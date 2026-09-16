<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $allowed = array_map(fn (string $role) => AccountType::from($role), $roles);

        if (! $user || ! in_array($user->role, $allowed, strict: true)) {
            abort(403, 'Accès réservé aux rôles : '.implode(', ', $roles).'.');
        }

        return $next($request);
    }
}
