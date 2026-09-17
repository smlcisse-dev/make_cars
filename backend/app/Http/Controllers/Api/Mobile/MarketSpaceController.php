<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\MarketSpaceAccountResource;
use App\Models\MarketSpaceAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketSpaceController extends Controller
{
    /**
     * Boutiques Market Space visibles publiquement (compte validé —
     * CLAUDE.md §5 règle 4), avec leur catalogue approuvé. Note moyenne
     * calculée via withAvg pour éviter le N+1 sur une liste (CLAUDE.md §5,
     * ajout v0.10).
     */
    public function index(): AnonymousResourceCollection
    {
        $accounts = MarketSpaceAccount::query()
            ->publiclyVisible()
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
            ->with(['openingHours', 'images', 'products' => fn ($query) => $query->where('status', ProductStatus::Approved)])
            ->paginate();

        return MarketSpaceAccountResource::collection($accounts);
    }

    public function show(MarketSpaceAccount $marketSpaceAccount): MarketSpaceAccountResource
    {
        abort_unless($marketSpaceAccount->isPubliclyVisible(), 404);

        $marketSpaceAccount->loadAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating');
        $marketSpaceAccount->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)]);
        $marketSpaceAccount->load([
            'openingHours',
            'images',
            'products' => fn ($query) => $query->where('status', ProductStatus::Approved),
        ]);

        return new MarketSpaceAccountResource($marketSpaceAccount);
    }
}
