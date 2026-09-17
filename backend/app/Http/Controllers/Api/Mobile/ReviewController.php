<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Quote;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    /**
     * Avis laissés par l'automobiliste authentifié, tous garages/boutiques
     * confondus.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reviews = $request->user()->reviews()->latest()->paginate();

        return ReviewResource::collection($reviews);
    }

    /**
     * Un devis facturé peut être noté une seule fois (CLAUDE.md §5, règle 7
     * et ajout v0.10).
     */
    public function storeForQuote(StoreReviewRequest $request, Quote $quote): JsonResponse
    {
        $review = $this->reviewService->createForQuote($quote, $request->user(), $request->validated());

        return $this->success(new ReviewResource($review), 'Avis publié.', 201);
    }

    /**
     * Une commande payée peut être notée une seule fois (CLAUDE.md §5, règle
     * 7 et ajout v0.10).
     */
    public function storeForOrder(StoreReviewRequest $request, Order $order): JsonResponse
    {
        $review = $this->reviewService->createForOrder($order, $request->user(), $request->validated());

        return $this->success(new ReviewResource($review), 'Avis publié.', 201);
    }

    /**
     * Avis publics d'un garage — les avis masqués par modération n'y
     * apparaissent jamais.
     */
    public function garageReviews(Garage $garage): AnonymousResourceCollection
    {
        abort_unless($garage->isPubliclyVisible(), 404);

        $reviews = $garage->reviews()->visible()->with('user')->latest()->paginate();

        return ReviewResource::collection($reviews);
    }

    /**
     * Avis publics d'un Market Space — les avis masqués par modération n'y
     * apparaissent jamais.
     */
    public function marketSpaceReviews(MarketSpaceAccount $marketSpaceAccount): AnonymousResourceCollection
    {
        abort_unless($marketSpaceAccount->isPubliclyVisible(), 404);

        $reviews = $marketSpaceAccount->reviews()->visible()->with('user')->latest()->paginate();

        return ReviewResource::collection($reviews);
    }
}
