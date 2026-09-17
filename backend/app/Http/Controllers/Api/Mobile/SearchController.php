<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\NearbySearchRequest;
use App\Http\Resources\NearbySearchResultResource;
use App\Services\GeoSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchController extends Controller
{
    public function __construct(private readonly GeoSearchService $geoSearchService) {}

    /**
     * Garages et Market Space approuvés (et non suspendus), avec recherche
     * par proximité (CLAUDE.md §5, ajout v0.13), par nom, par service
     * proposé, par tri au choix du client (distance ou note — CLAUDE.md §5,
     * ajout v0.14) et par nom de produit (les deux types de vendeurs —
     * CLAUDE.md §5, ajout v0.15). Tous ces critères sont combinables ; seuls
     * le rayon et le tri par distance exigent une position (validés en
     * amont). Publique comme les listes garages/market-space-accounts : un
     * automobiliste en panne n'a pas forcément de session ouverte.
     */
    public function nearby(NearbySearchRequest $request): AnonymousResourceCollection
    {
        $results = $this->geoSearchService->search(
            latitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
            radiusKm: $request->filled('radius_km') ? (float) $request->input('radius_km') : null,
            type: $request->input('type', 'both'),
            page: $request->integer('page', 1),
            name: $request->filled('name') ? (string) $request->input('name') : null,
            serviceCategory: $request->filled('service_category') ? ServiceCategory::from($request->input('service_category')) : null,
            serviceId: $request->filled('service_id') ? $request->integer('service_id') : null,
            sort: $request->filled('sort') ? $request->input('sort') : null,
            productName: $request->filled('product_name') ? (string) $request->input('product_name') : null,
        );

        return NearbySearchResultResource::collection($results);
    }
}
