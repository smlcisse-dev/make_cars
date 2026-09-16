<?php

namespace App\Enums;

/**
 * Cycle de vie d'une commande (achat isolé de pièces, sans prestation —
 * CLAUDE.md §5, règle 11 et ajout v0.9). Pas de négociation ni de validation
 * client à part le paiement lui-même : le prix est déjà fixé au catalogue.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
