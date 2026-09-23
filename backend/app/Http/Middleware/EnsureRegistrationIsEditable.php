<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verrouille les écritures du profil (informations, horaires, photos,
 * informations légales, document) pendant l'examen du dossier par
 * l'administrateur (CLAUDE.md §5, ajout v0.26) : ce qu'il examine ne doit
 * pas changer sous ses yeux.
 */
class EnsureRegistrationIsEditable
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->professionalRegistration?->status === RegistrationStatus::Pending) {
            return response()->json([
                'message' => 'Votre dossier est en cours d\'examen : le profil ne peut pas être modifié pour le moment.',
                'code' => 'registration_under_review',
            ], 409);
        }

        return $next($request);
    }
}
