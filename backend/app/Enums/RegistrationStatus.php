<?php

namespace App\Enums;

/**
 * Statut du dossier d'inscription professionnelle (CLAUDE.md §5, règle 4).
 * Tant que le statut n'est pas Approved, le compte associé ne peut rien
 * publier publiquement (services, produits).
 */
enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
