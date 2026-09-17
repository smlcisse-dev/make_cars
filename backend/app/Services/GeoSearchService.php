<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Support\NearbySearchResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Recherche par proximité géographique, indépendante de tout fournisseur de
 * cartographie externe (Google Maps, Mapbox...) — uniquement la formule de
 * Haversine sur les coordonnées déjà stockées (CLAUDE.md §5, ajout v0.13).
 * Clôt le point ouvert §7 sur la recherche géolocalisée ; l'affichage
 * visuel sur une carte reste un sujet frontend séparé, sans dépendance à ce
 * module.
 *
 * Le calcul de distance est fait en PHP plutôt qu'en SQL trigonométrique :
 * portable entre SQLite (tests) et PostgreSQL (production) sans extension
 * particulière, et largement suffisant à l'échelle actuelle (nombre de
 * garages/boutiques d'un pays). À revoir (index géospatial type PostGIS/
 * earthdistance) si le volume de professionnels grandit significativement —
 * cf. exigence de performance §6 (< 2-3s).
 */
class GeoSearchService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @param  'garage'|'market_space'|'both'  $type
     */
    public function search(float $latitude, float $longitude, ?float $radiusKm, string $type, int $page, int $perPage = 15): LengthAwarePaginator
    {
        $radiusKm = min($radiusKm ?? (float) config('geo.default_search_radius_km'), (float) config('geo.max_search_radius_km'));

        $results = collect();

        if ($type !== 'market_space') {
            $results = $results->merge($this->searchGarages($latitude, $longitude));
        }

        if ($type !== 'garage') {
            $results = $results->merge($this->searchMarketSpaces($latitude, $longitude));
        }

        $results = $results
            ->filter(fn (NearbySearchResult $result) => $result->distanceKm <= $radiusKm)
            ->sortBy('distanceKm')
            ->values();

        return new LengthAwarePaginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * @return Collection<int, NearbySearchResult>
     */
    private function searchGarages(float $latitude, float $longitude): Collection
    {
        return Garage::query()
            ->publiclyVisible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
            ->with(['images', 'openingHours'])
            ->get()
            ->map(fn (Garage $garage) => new NearbySearchResult(
                type: 'garage',
                id: $garage->id,
                name: $garage->name,
                address: $garage->address,
                photoUrl: $garage->images->first()?->url(),
                distanceKm: $this->distanceKm($latitude, $longitude, (float) $garage->latitude, (float) $garage->longitude),
                averageRating: $garage->average_rating !== null ? (float) $garage->average_rating : null,
                reviewsCount: (int) $garage->reviews_count,
                isOpenNow: $garage->isOpenNow(),
            ));
    }

    /**
     * @return Collection<int, NearbySearchResult>
     */
    private function searchMarketSpaces(float $latitude, float $longitude): Collection
    {
        return MarketSpaceAccount::query()
            ->publiclyVisible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
            ->with(['images', 'openingHours'])
            ->get()
            ->map(fn (MarketSpaceAccount $account) => new NearbySearchResult(
                type: 'market_space',
                id: $account->id,
                name: $account->name,
                address: $account->address,
                photoUrl: $account->images->first()?->url(),
                distanceKm: $this->distanceKm($latitude, $longitude, (float) $account->latitude, (float) $account->longitude),
                averageRating: $account->average_rating !== null ? (float) $account->average_rating : null,
                reviewsCount: (int) $account->reviews_count,
                isOpenNow: $account->isOpenNow(),
            ));
    }

    /**
     * Formule de Haversine — distance à vol d'oiseau en kilomètres entre
     * deux points géographiques.
     */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
