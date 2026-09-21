<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Admin\ModerateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    /**
     * Supervision admin en lecture (CLAUDE.md §5 règle 8), y compris les
     * avis déjà masqués — nécessaire pour auditer la modération elle-même.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('reviewable_type'), fn ($query) => $query->where(
                'reviewable_type',
                $request->string('reviewable_type') === 'garage' ? Garage::class : MarketSpaceAccount::class
            ))
            ->with(['reviewable', 'user', 'moderatedBy'])
            ->latest()
            ->paginate();

        return ReviewResource::collection($reviews);
    }

    public function show(Review $review): ReviewResource
    {
        return new ReviewResource($review->load(['reviewable', 'user', 'moderatedBy']));
    }

    /**
     * Masque un avis abusif/diffamatoire — motif obligatoire, jamais une
     * suppression SQL pour garder la preuve de la modération elle-même
     * (CLAUDE.md §5, ajout v0.10).
     */
    public function moderate(ModerateReviewRequest $request, Review $review): JsonResponse
    {
        abort_if($review->status === ReviewStatus::Hidden, 403, 'Cet avis est déjà masqué.');

        $review = $this->reviewService->moderate($review, $request->user(), $request->string('reason')->toString())
            ->load(['reviewable', 'user', 'moderatedBy']);

        return $this->success(new ReviewResource($review), 'Avis masqué.');
    }
}
