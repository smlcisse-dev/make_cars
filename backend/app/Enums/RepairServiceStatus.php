<?php

namespace App\Enums;

/**
 * Statut de validation d'un service de réparation proposé par un garage.
 * Toute création ou modification repasse en Pending ; visible côté app
 * mobile uniquement une fois Approved (CLAUDE.md §5, règle 5).
 */
enum RepairServiceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
