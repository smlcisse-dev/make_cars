<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;

/**
 * `meta` des réponses de la page profil d'un professionnel (Garage ou Market
 * Space) : complétude du profil public (v0.20), complétude des informations
 * légales, état du dossier et informations légales elles-mêmes (CLAUDE.md
 * §5, ajout v0.26). Les informations légales privées ne sont renvoyées
 * qu'ici, à leur propriétaire — jamais dans une ressource publique.
 */
trait BuildsProfessionalProfileMeta
{
    /**
     * @return array<string, mixed>
     */
    protected function professionalProfileMeta(Garage|MarketSpaceAccount $profile, ?ProfessionalRegistration $registration): array
    {
        return [
            'profile_status' => $profile->profileStatus(),
            ...($registration !== null ? $this->registrationMeta($registration) : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrationMeta(ProfessionalRegistration $registration): array
    {
        return [
            'legal_status' => $registration->legalStatus(),
            'registration' => [
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'rejection_reason' => $registration->rejection_reason,
                'submitted_at' => $registration->submitted_at?->toIso8601String(),
            ],
            'legal' => [
                'business_registration_number' => $registration->business_registration_number,
                'ifu' => $registration->ifu,
                'npi' => $registration->npi,
                'has_business_registration_document' => $registration->businessRegistrationDocument()->exists(),
            ],
        ];
    }
}
