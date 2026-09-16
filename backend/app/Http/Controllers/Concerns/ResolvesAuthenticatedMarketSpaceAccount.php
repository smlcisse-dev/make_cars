<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MarketSpaceAccount;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedMarketSpaceAccount
{
    /**
     * Le compte Market Space n'existe qu'après approbation du dossier KYC
     * (créé automatiquement à ce moment-là — voir MarketSpaceAccountService).
     */
    protected function authenticatedMarketSpaceAccount(Request $request): MarketSpaceAccount
    {
        $account = $request->user()->marketSpaceAccount;

        abort_if($account === null, 404, "Compte Market Space introuvable : le compte n'est pas encore validé par un administrateur.");

        return $account;
    }
}
