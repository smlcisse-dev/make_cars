<?php

namespace App\Enums;

/**
 * Décision du client sur une version précise du devis — jamais déduite d'un
 * message du chat, toujours une action authentifiée et tracée
 * (CLAUDE.md §5, ajout v0.8).
 */
enum QuoteVersionDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
