<?php

namespace App\Support;

/**
 * Résultat unifié de la recherche (Garage ou Market Space — CLAUDE.md §5,
 * ajouts v0.13, v0.14 et v0.15), indépendant de tout fournisseur de
 * cartographie externe : uniquement des données, l'affichage sur une carte
 * est un sujet frontend distinct.
 *
 * distanceKm est nullable : la recherche par nom/service peut être utilisée
 * sans position transmise par le client (CLAUDE.md §5, ajout v0.14), auquel
 * cas aucune distance n'est calculable.
 */
final class NearbySearchResult
{
    /**
     * @param  array<int, MatchedProductResult>  $matchedProducts  Produits approuvés de ce vendeur correspondant à la recherche par nom de produit (CLAUDE.md §5, ajout v0.15) — vide hors de ce filtre.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $photoUrl,
        public readonly ?float $distanceKm,
        public readonly ?float $averageRating,
        public readonly int $reviewsCount,
        public readonly ?bool $isOpenNow,
        public readonly array $matchedProducts = [],
    ) {}
}
