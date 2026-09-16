<?php

namespace App\Enums;

/**
 * Justificatifs exigés pour toute inscription professionnelle (CLAUDE.md §5,
 * règle 4) : registre de commerce / IFU-RCCM, et au moins une photo du local.
 */
enum RegistrationDocumentType: string
{
    case BusinessRegistration = 'business_registration';
    case PremisesPhoto = 'premises_photo';
}
