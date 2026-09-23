<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque tout l'espace professionnel (Garagiste/Market Space) tant que le
 * profil n'est pas complet — seules les routes de profil restent accessibles
 * (CLAUDE.md §5, ajout v0.20). Toujours placé après `registration.approved`
 * (CLAUDE.md §5, ajout v0.26) : il ne concerne donc qu'un compte approuvé.
 */
class EnsureProfileIsComplete
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->professionalProfile();

        if ($profile !== null && ! $profile->isProfileComplete()) {
            return response()->json([
                'message' => 'Votre profil doit être complété avant d\'accéder à cet espace.',
                'code' => 'profile_incomplete',
                'missing_fields' => $profile->missingProfileFields(),
            ], 403);
        }

        return $next($request);
    }
}
