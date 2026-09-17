<?php

namespace App\Support;

/**
 * Résultat unifié de la recherche géolocalisée (Garage ou Market Space —
 * CLAUDE.md §5, ajout v0.13), indépendant de tout fournisseur de
 * cartographie externe : uniquement des données, l'affichage sur une carte
 * est un sujet frontend distinct.
 */
final class NearbySearchResult
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $photoUrl,
        public readonly float $distanceKm,
        public readonly ?float $averageRating,
        public readonly int $reviewsCount,
        public readonly ?bool $isOpenNow,
    ) {}
}
