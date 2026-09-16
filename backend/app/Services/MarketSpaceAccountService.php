<?php

namespace App\Services;

use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;

class MarketSpaceAccountService
{
    /**
     * Pré-remplit le profil Market Space à partir du dossier KYC validé
     * (nom, adresse), pendant public du compte pour le catalogue de pièces.
     */
    public function createFromRegistration(ProfessionalRegistration $registration): MarketSpaceAccount
    {
        return $registration->user->marketSpaceAccount()->create([
            'name' => $registration->structure_name,
            'address' => $registration->address,
        ]);
    }
}
