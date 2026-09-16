<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ProductStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Models\MarketSpaceAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketSpaceController extends Controller
{
    /**
     * Boutiques Market Space visibles publiquement (compte validé —
     * CLAUDE.md §5 règle 4), avec leur catalogue approuvé.
     */
    public function index(): AnonymousResourceCollection
    {
        $accounts = MarketSpaceAccount::query()
            ->whereHas('user.professionalRegistration', fn ($query) => $query->where('status', RegistrationStatus::Approved))
            ->with(['products' => fn ($query) => $query->where('status', ProductStatus::Approved)])
            ->paginate();

        return MarketSpaceAccountResource::collection($accounts);
    }

    public function show(MarketSpaceAccount $marketSpaceAccount): MarketSpaceAccountResource
    {
        abort_unless($marketSpaceAccount->isPubliclyVisible(), 404);

        $marketSpaceAccount->load(['products' => fn ($query) => $query->where('status', ProductStatus::Approved)]);

        return new MarketSpaceAccountResource($marketSpaceAccount);
    }
}
