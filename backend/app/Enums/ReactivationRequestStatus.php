<?php

namespace App\Enums;

/**
 * Statut d'une demande de réactivation d'un compte suspendu (CLAUDE.md §5,
 * ajout v0.28) : Pending → Accepted (l'admin réactive le compte) | Refused
 * (motif obligatoire, le compte reste suspendu).
 */
enum ReactivationRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
        };
    }
}
