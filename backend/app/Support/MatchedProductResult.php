<?php

namespace App\Support;

/**
 * Produit approuvé correspondant à la recherche par nom de produit
 * (CLAUDE.md §5, ajout v0.15), rattaché au résultat de recherche du vendeur
 * (Garage ou Market Space) qui le propose — pour que le client voie
 * directement ce qu'il cherche sans ouvrir chaque fiche.
 */
final class MatchedProductResult
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price,
    ) {}
}
