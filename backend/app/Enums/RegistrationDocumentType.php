<?php

namespace App\Enums;

/**
 * Justificatifs d'une inscription professionnelle (CLAUDE.md §5, règle 4).
 * Depuis v0.26, seul le document du registre de commerce est envoyé (étape
 * profil) : l'admin voit les photos du profil comme photos du local.
 * PremisesPhoto reste pour les dossiers antérieurs qui en ont.
 */
enum RegistrationDocumentType: string
{
    case BusinessRegistration = 'business_registration';
    case PremisesPhoto = 'premises_photo';

    public function label(): string
    {
        return match ($this) {
            self::BusinessRegistration => 'Registre de commerce / IFU-RCCM',
            self::PremisesPhoto => 'Photo du local',
        };
    }
}
