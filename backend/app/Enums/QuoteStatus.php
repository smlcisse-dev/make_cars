<?php

namespace App\Enums;

/**
 * Cycle de vie du devis/facture, rattaché à un RDV confirmé (CLAUDE.md §5,
 * ajout v0.8). "Draft" n'est jamais observé en dehors d'une version en cours
 * d'édition : la création d'un devis passe toujours par une version brouillon
 * explicite avant envoi.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Negotiating = 'negotiating';
    case InProgress = 'in_progress';
    case Invoiced = 'invoiced';
    case Abandoned = 'abandoned';
}
