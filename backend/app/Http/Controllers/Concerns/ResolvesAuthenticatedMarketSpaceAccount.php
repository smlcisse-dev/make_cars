<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MarketSpaceAccount;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedMarketSpaceAccount
{
    /**
     * Le profil Market Space est créé dès la vérification de l'email
     * (CLAUDE.md §5, ajout v0.26) : ce 404 n'est plus qu'un filet de sécurité
     * pour un compte incohérent (profil supprimé ou jamais créé).
     */
    protected function authenticatedMarketSpaceAccount(Request $request): MarketSpaceAccount
    {
        $account = $request->user()->marketSpaceAccount;

        abort_if($account === null, 404, 'Profil Market Space introuvable pour ce compte. Contactez le support.');

        return $account;
    }
}
