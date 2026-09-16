<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Garage;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedGarage
{
    /**
     * Le profil Garage n'existe qu'après approbation du dossier KYC
     * (créé automatiquement à ce moment-là — voir ProfessionalRegistrationService).
     */
    protected function authenticatedGarage(Request $request): Garage
    {
        $garage = $request->user()->garage;

        abort_if($garage === null, 404, "Profil garage introuvable : le compte n'est pas encore validé par un administrateur.");

        return $garage;
    }
}
