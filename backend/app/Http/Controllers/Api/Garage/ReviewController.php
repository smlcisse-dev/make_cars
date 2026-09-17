<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Le garage consulte ses avis reçus en lecture seule : il ne peut ni les
 * modifier ni les supprimer (CLAUDE.md §5, règle 7 et ajout v0.10) — y
 * compris les avis masqués par modération, pour transparence sur son propre
 * historique.
 */
class ReviewController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function index(Request $request): AnonymousResourceCollection
    {
        $reviews = $this->authenticatedGarage($request)->reviews()->with('user')->latest()->paginate();

        return ReviewResource::collection($reviews);
    }
}
