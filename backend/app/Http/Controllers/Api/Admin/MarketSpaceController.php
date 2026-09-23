<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Models\MarketSpaceAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketSpaceController extends Controller
{
    /**
     * Supervision admin en lecture (CLAUDE.md §5, règle 8) : comptes Market
     * Space au dossier approuvé, suspendus compris. Les dossiers en cours
     * (profil créé dès la vérification de l'email) se consultent via
     * /admin/registrations (CLAUDE.md §5, ajout v0.26).
     */
    public function index(): AnonymousResourceCollection
    {
        $accounts = MarketSpaceAccount::query()->withApprovedRegistration()->with(['user', 'openingHours', 'images'])->paginate();

        return MarketSpaceAccountResource::collection($accounts);
    }

    public function show(MarketSpaceAccount $marketSpaceAccount): MarketSpaceAccountResource
    {
        abort_unless($marketSpaceAccount->hasApprovedRegistration(), 404, 'Structure introuvable : son dossier n\'est pas approuvé (voir /admin/registrations).');

        return new MarketSpaceAccountResource($marketSpaceAccount->load(['openingHours', 'images', 'department', 'commune', 'arrondissement']));
    }
}
