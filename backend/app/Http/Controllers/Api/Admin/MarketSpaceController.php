<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Models\MarketSpaceAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketSpaceController extends Controller
{
    /**
     * Supervision admin : accès en lecture à tous les comptes Market Space,
     * validés ou non (CLAUDE.md §5, règle 8).
     */
    public function index(): AnonymousResourceCollection
    {
        $accounts = MarketSpaceAccount::query()->with(['user', 'openingHours', 'images'])->paginate();

        return MarketSpaceAccountResource::collection($accounts);
    }

    public function show(MarketSpaceAccount $marketSpaceAccount): MarketSpaceAccountResource
    {
        return new MarketSpaceAccountResource($marketSpaceAccount->load(['openingHours', 'images']));
    }
}
