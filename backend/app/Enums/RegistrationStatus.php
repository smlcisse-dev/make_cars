<?php

namespace App\Enums;

/**
 * Statut du dossier d'inscription professionnelle (CLAUDE.md §5, règle 4).
 * Tant que le statut n'est pas Approved, le compte associé ne peut rien
 * publier publiquement (services, produits).
 *
 * Cycle (CLAUDE.md §5, ajout v0.26) : ProfileIncomplete → (soumission)
 * Pending → Approved | Rejected ; Rejected → (corrections, nouvelle
 * soumission) Pending.
 */
enum RegistrationStatus: string
{
    case ProfileIncomplete = 'profile_incomplete';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::ProfileIncomplete => 'Profil à compléter',
            self::Pending => 'En attente',
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
        };
    }
}
