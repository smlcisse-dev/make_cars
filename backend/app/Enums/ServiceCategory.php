<?php

namespace App\Enums;

/**
 * Liste fixe des catégories de service, définie par la plateforme — un
 * garagiste choisit dans cette liste, pas de texte libre (CLAUDE.md §5,
 * ajout v0.6). Toute évolution de cette liste passe par une modification de
 * code (déploiement), pas par une interface d'administration.
 */
enum ServiceCategory: string
{
    case EntretienCourant = 'entretien_courant';
    case FreinageSuspension = 'freinage_suspension';
    case Pneumatiques = 'pneumatiques';
    case ElectriciteElectronique = 'electricite_electronique';
    case ClimatisationRefroidissement = 'climatisation_refroidissement';
    case Carrosserie = 'carrosserie';
    case DiagnosticControle = 'diagnostic_controle';
    case AutreDivers = 'autre_divers';

    public function label(): string
    {
        return match ($this) {
            self::EntretienCourant => 'Entretien courant',
            self::FreinageSuspension => 'Freinage & suspension',
            self::Pneumatiques => 'Pneumatiques',
            self::ElectriciteElectronique => 'Électricité & électronique',
            self::ClimatisationRefroidissement => 'Climatisation & refroidissement',
            self::Carrosserie => 'Carrosserie',
            self::DiagnosticControle => 'Diagnostic & contrôle',
            self::AutreDivers => 'Autre/Divers',
        };
    }
}
