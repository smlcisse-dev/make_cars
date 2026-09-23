<?php

namespace App\Support;

use App\Enums\AccountType;

/**
 * Liens vers la SPA Vue (frontend-web) insérés dans les emails
 * (`config('app.frontend_url')`, CLAUDE.md §5 ajout v0.26) — distincts des
 * URL de l'API, qui ne sert aucune page.
 */
class FrontendUrl
{
    public static function to(string $path): string
    {
        return rtrim(config('app.frontend_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Page profil de l'espace professionnel du rôle donné : le garde de
     * navigation du frontend fait passer par la connexion puis y ramène.
     */
    public static function professionalProfile(AccountType $role): string
    {
        return self::to(($role === AccountType::MarketSpace ? 'market-space' : 'garage').'/profile');
    }

    public static function login(): string
    {
        return self::to('login');
    }
}
