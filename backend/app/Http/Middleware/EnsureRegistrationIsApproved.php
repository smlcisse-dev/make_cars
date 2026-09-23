<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme toutes les routes métier de l'espace pro (services, produits, RDV,
 * devis, commandes, chat…) tant que le dossier d'inscription n'est pas
 * approuvé (CLAUDE.md §5, ajout v0.26). Placé avant `profile.complete` : un
 * dossier non approuvé est la raison première du blocage. La suspension
 * (statut resté Approved) n'est pas concernée ici (CLAUDE.md §5, ajout v0.6).
 */
class EnsureRegistrationIsApproved
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $registration = $request->user()?->professionalRegistration;

        if ($registration?->status !== RegistrationStatus::Approved) {
            return response()->json([
                'message' => 'Votre dossier doit être validé par un administrateur avant d\'accéder à cet espace.',
                'code' => 'registration_not_approved',
                'registration_status' => $registration?->status->value,
            ], 403);
        }

        return $next($request);
    }
}
