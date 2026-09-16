<?php

namespace App\Enums;

/**
 * Les quatre acteurs de la plateforme (CLAUDE.md §3). Un utilisateur n'a
 * qu'un seul type : un garage qui tient aussi une boutique crée deux comptes
 * distincts (un par type), rattachables à la même structure.
 */
enum AccountType: string
{
    case Automobiliste = 'automobiliste';
    case Garagiste = 'garagiste';
    case MarketSpace = 'market_space';
    case Admin = 'admin';

    public function requiresProfessionalValidation(): bool
    {
        return match ($this) {
            self::Garagiste, self::MarketSpace => true,
            self::Automobiliste, self::Admin => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Automobiliste => 'Automobiliste',
            self::Garagiste => 'Compte Garagiste',
            self::MarketSpace => 'Compte Market Space',
            self::Admin => 'Administrateur',
        };
    }
}
