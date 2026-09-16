<?php

namespace App\Enums;

/**
 * Statut de validation d'un produit (mini-boutique Garage ou Market Space).
 * Tout produit ajouté ou modifié repasse en Pending et n'est visible côté
 * app mobile qu'une fois Approved (CLAUDE.md §5, règle 5).
 */
enum ProductStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
