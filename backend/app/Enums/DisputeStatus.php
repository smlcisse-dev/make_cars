<?php

namespace App\Enums;

/**
 * Cycle de vie d'une réclamation (CLAUDE.md §5, ajout v0.11). "UnderReview"
 * est atteint dès qu'une réponse est demandée par l'admin ou envoyée par le
 * professionnel. "Closed" est un pas distinct de la résolution : l'admin
 * clôture explicitement le dossier une fois la décision (et son éventuelle
 * suite) actée.
 */
enum DisputeStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ResolvedFounded = 'resolved_founded';
    case ResolvedRejected = 'resolved_rejected';
    case Closed = 'closed';
}
