<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Garage;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedGarage
{
    /**
     * Le profil Garage est créé dès la vérification de l'email (CLAUDE.md §5,
     * ajout v0.26) : ce 404 n'est plus qu'un filet de sécurité pour un compte
     * incohérent (profil supprimé ou jamais créé).
     */
    protected function authenticatedGarage(Request $request): Garage
    {
        $garage = $request->user()->garage;

        abort_if($garage === null, 404, 'Profil garage introuvable pour ce compte. Contactez le support.');

        return $garage;
    }
}
