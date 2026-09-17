<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\NearbySearchRequest;
use App\Http\Resources\NearbySearchResultResource;
use App\Services\GeoSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    public function __construct(private readonly GeoSearchService $geoSearchService) {}

    /**
     * Garages et Market Space approuvés (et non suspendus) triés par
     * proximité à partir de la position actuelle de l'automobiliste
     * (CLAUDE.md §5, ajout v0.13). Publique comme les listes garages/
     * market-space-accounts : un automobiliste en panne n'a pas forcément
     * de session ouverte.
     */
    public function nearby(NearbySearchRequest $request): AnonymousResourceCollection
    {
        $results = $this->geoSearchService->search(
            latitude: (float) $request->input('latitude'),
            longitude: (float) $request->input('longitude'),
            radiusKm: $request->filled('radius_km') ? (float) $request->input('radius_km') : null,
            type: $request->input('type', 'both'),
            page: $request->integer('page', 1),
        );

        return NearbySearchResultResource::collection($results);
    }
}
