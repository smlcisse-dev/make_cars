<?php

namespace App\Enums;

/**
 * Suite donnée par l'admin à une réclamation jugée fondée — choisie
 * librement selon la gravité évaluée, aucune sanction automatique
 * (CLAUDE.md §5, ajout v0.11). "Suspension" réutilise le mécanisme déjà
 * construit (ProfessionalRegistrationService::suspend) ; "Warning" ne
 * déclenche aucune autre conséquence que sa trace sur le dossier lui-même.
 */
enum DisputeResolutionAction: string
{
    case Warning = 'warning';
    case Suspension = 'suspension';
}
