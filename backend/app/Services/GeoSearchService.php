<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\RepairServiceStatus;
use App\Enums\ReviewStatus;
use App\Enums\ServiceCategory;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Product;
use App\Support\MatchedProductResult;
use App\Support\NearbySearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Recherche de garages/Market Space, indépendante de tout fournisseur de
 * cartographie externe (Google Maps, Mapbox...) — uniquement la formule de
 * Haversine sur les coordonnées déjà stockées pour la proximité (CLAUDE.md
 * §5, ajout v0.13), complétée par une recherche par nom, un filtre par
 * service proposé, un choix de tri (CLAUDE.md §5, ajout v0.14), une
 * recherche par nom de produit (CLAUDE.md §5, ajout v0.15) et un filtre par
 * tranche de prix sur les produits (CLAUDE.md §5, ajout v0.16).
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
     * La position (latitude/longitude) est désormais optionnelle : une
     * recherche par nom et/ou par service doit pouvoir fonctionner seule,
     * sans filtre géographique (CLAUDE.md §5, ajout v0.14). Le rayon et le
     * tri par distance restent impossibles sans position — la couche
     * validation (NearbySearchRequest) l'impose déjà avant d'arriver ici.
     *
     * Le filtre service (catégorie ou service précis) ne peut jamais être
     * satisfait par un Market Space (qui ne fait pas de réparation — CLAUDE.md
     * §5, ajout v0.6) : sa présence exclut donc les Market Space du résultat,
     * quel que soit le `type` demandé. Le filtre par nom de produit, lui,
     * concerne les deux types de vendeurs (mini-boutique Garage et Market
     * Space partagent la même logique catalogue — CLAUDE.md §5, ajout v0.4)
     * et n'exclut donc jamais l'un ou l'autre. Le filtre de tranche de prix
     * (`minPrice`/`maxPrice`) fonctionne de la même façon, seul ou combiné
     * à `productName` (CLAUDE.md §5, ajout v0.16).
     *
     * @param  'garage'|'market_space'|'both'  $type
     * @param  'distance'|'rating'|null  $sort  Par défaut 'distance' si une
     *                                          position est fournie, sinon 'rating'.
     */
    public function search(
        ?float $latitude,
        ?float $longitude,
        ?float $radiusKm,
        string $type,
        int $page,
        int $perPage = 15,
        ?string $name = null,
        ?ServiceCategory $serviceCategory = null,
        ?int $serviceId = null,
        ?string $sort = null,
        ?string $productName = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
    ): LengthAwarePaginator {
        $hasPosition = $latitude !== null && $longitude !== null;
        $sort ??= $hasPosition ? 'distance' : 'rating';
        $hasServiceFilter = $serviceCategory !== null || $serviceId !== null;

        $radiusKm = $hasPosition
            ? min($radiusKm ?? (float) config('geo.default_search_radius_km'), (float) config('geo.max_search_radius_km'))
            : null;

        $results = collect();

        if ($type !== 'market_space') {
            $results = $results->merge($this->searchGarages($latitude, $longitude, $name, $serviceCategory, $serviceId, $productName, $minPrice, $maxPrice));
        }

        if ($type !== 'garage' && ! $hasServiceFilter) {
            $results = $results->merge($this->searchMarketSpaces($latitude, $longitude, $name, $productName, $minPrice, $maxPrice));
        }

        if ($radiusKm !== null) {
            $results = $results->filter(fn (NearbySearchResult $result) => $result->distanceKm !== null && $result->distanceKm <= $radiusKm);
        }

        $results = $this->sortResults($results, $sort)->values();

        return new LengthAwarePaginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * @param  Collection<int, NearbySearchResult>  $results
     * @param  'distance'|'rating'  $sort
     * @return Collection<int, NearbySearchResult>
     */
    private function sortResults(Collection $results, string $sort): Collection
    {
        if ($sort === 'rating') {
            return $results->sort(function (NearbySearchResult $a, NearbySearchResult $b) {
                $ratingA = $a->averageRating ?? -1;
                $ratingB = $b->averageRating ?? -1;

                return $ratingB <=> $ratingA ?: $a->name <=> $b->name;
            });
        }

        return $results->sortBy('distanceKm');
    }

    /**
     * @return Collection<int, NearbySearchResult>
     */
    private function searchGarages(?float $latitude, ?float $longitude, ?string $name, ?ServiceCategory $serviceCategory, ?int $serviceId, ?string $productName, ?float $minPrice, ?float $maxPrice): Collection
    {
        $hasProductFilter = $productName !== null || $minPrice !== null || $maxPrice !== null;

        return Garage::query()
            ->publiclyVisible()
            ->when($latitude !== null && $longitude !== null, fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when($serviceCategory !== null || $serviceId !== null, fn (Builder $query) => $query->whereHas(
                'services',
                function (Builder $serviceQuery) use ($serviceCategory, $serviceId) {
                    $serviceQuery->where('status', RepairServiceStatus::Approved)->where('is_active', true);

                    if ($serviceCategory !== null) {
                        $serviceQuery->where('category', $serviceCategory);
                    }

                    if ($serviceId !== null) {
                        $serviceQuery->where('id', $serviceId);
                    }
                }
            ))
            ->when($hasProductFilter, fn (Builder $query) => $query->with(['products' => fn ($q) => $this->productsQuery($q, $minPrice, $maxPrice)]))
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
            ->with(['images', 'openingHours'])
            ->get()
            ->filter(fn (Garage $garage) => $this->matchesName($garage->name, $name))
            ->map(fn (Garage $garage) => new NearbySearchResult(
                type: 'garage',
                id: $garage->id,
                name: $garage->name,
                address: $garage->address,
                photoUrl: $garage->images->first()?->url(),
                distanceKm: $latitude !== null && $longitude !== null
                    ? $this->distanceKm($latitude, $longitude, (float) $garage->latitude, (float) $garage->longitude)
                    : null,
                averageRating: $garage->average_rating !== null ? (float) $garage->average_rating : null,
                reviewsCount: (int) $garage->reviews_count,
                isOpenNow: $garage->isOpenNow(),
                matchedProducts: $hasProductFilter ? $this->matchedProducts($garage, $productName) : [],
            ))
            ->filter(fn (NearbySearchResult $result) => ! $hasProductFilter || $result->matchedProducts !== []);
    }

    /**
     * @return Collection<int, NearbySearchResult>
     */
    private function searchMarketSpaces(?float $latitude, ?float $longitude, ?string $name, ?string $productName, ?float $minPrice, ?float $maxPrice): Collection
    {
        $hasProductFilter = $productName !== null || $minPrice !== null || $maxPrice !== null;

        return MarketSpaceAccount::query()
            ->publiclyVisible()
            ->when($latitude !== null && $longitude !== null, fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when($hasProductFilter, fn (Builder $query) => $query->with(['products' => fn ($q) => $this->productsQuery($q, $minPrice, $maxPrice)]))
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', ReviewStatus::Visible)], 'rating')
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', ReviewStatus::Visible)])
            ->with(['images', 'openingHours'])
            ->get()
            ->filter(fn (MarketSpaceAccount $account) => $this->matchesName($account->name, $name))
            ->map(fn (MarketSpaceAccount $account) => new NearbySearchResult(
                type: 'market_space',
                id: $account->id,
                name: $account->name,
                address: $account->address,
                photoUrl: $account->images->first()?->url(),
                distanceKm: $latitude !== null && $longitude !== null
                    ? $this->distanceKm($latitude, $longitude, (float) $account->latitude, (float) $account->longitude)
                    : null,
                averageRating: $account->average_rating !== null ? (float) $account->average_rating : null,
                reviewsCount: (int) $account->reviews_count,
                isOpenNow: $account->isOpenNow(),
                matchedProducts: $hasProductFilter ? $this->matchedProducts($account, $productName) : [],
            ))
            ->filter(fn (NearbySearchResult $result) => ! $hasProductFilter || $result->matchedProducts !== []);
    }

    /**
     * Contraintes communes de la relation `products` chargée pour les
     * filtres produit (statut approuvé, et tranche de prix — CLAUDE.md §5,
     * ajout v0.16). Le prix est filtré en SQL, contrairement au nom
     * (comparaison insensible casse/accents, faite en PHP par
     * matchedProducts()) : c'est une comparaison numérique exacte, sans les
     * problèmes de portabilité de LOWER() évoqués pour le nom.
     */
    private function productsQuery(Relation $query, ?float $minPrice, ?float $maxPrice): Relation
    {
        $query->where('status', ProductStatus::Approved);

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * Recherche partielle, insensible à la casse et aux accents, sur le nom
     * (CLAUDE.md §5, ajout v0.14 pour le nom du vendeur, v0.15 pour le nom
     * de produit — même comparaison réutilisée dans les deux cas).
     * Comparaison faite en PHP plutôt qu'en SQL (LOWER() ne gère pas les
     * caractères accentués correctement sous SQLite, et un simple LOWER()
     * ne fait de toute façon pas de repli sur les accents sous PostgreSQL) —
     * même choix de portabilité que le calcul de distance, à l'échelle
     * actuelle du volume de professionnels/produits (§6).
     */
    private function matchesName(string $candidateName, ?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        return str_contains($this->normalizeForSearch($candidateName), $this->normalizeForSearch($name));
    }

    /**
     * Translittère vers de l'ASCII minuscule (ext-intl, ICU) pour une
     * comparaison insensible à la casse et aux accents portable entre
     * environnements — contrairement à `iconv(..., 'ASCII//TRANSLIT', ...)`,
     * dont le support de la table de translittération varie selon
     * l'implémentation d'iconv du système (ex. musl/Alpine vs glibc).
     */
    private function normalizeForSearch(string $value): string
    {
        $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

        return $normalized !== false ? $normalized : mb_strtolower($value);
    }

    /**
     * Produits approuvés du vendeur (Garage ou Market Space), déjà
     * restreints à la tranche de prix demandée par productsQuery() au
     * chargement de la relation (CLAUDE.md §5, ajout v0.16), et dont le nom
     * correspond en plus à la recherche par nom de produit quand elle est
     * fournie (CLAUDE.md §5, ajout v0.15) — même comparaison insensible
     * casse/accents que matchesName(). N'est appelée que lorsqu'au moins un
     * filtre produit est actif (voir $hasProductFilter des appelants) ; la
     * relation `products` est alors garantie chargée.
     *
     * @return array<int, MatchedProductResult>
     */
    private function matchedProducts(Garage|MarketSpaceAccount $sellable, ?string $productName): array
    {
        return $sellable->products
            ->filter(fn (Product $product) => $this->matchesName($product->name, $productName))
            ->map(fn (Product $product) => new MatchedProductResult($product->id, $product->name, (float) $product->price))
            ->values()
            ->all();
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
