<?php

namespace App\Enums;

/**
 * Nature d'une ligne de devis (CLAUDE.md §5, ajout v0.8). Les frais de
 * diagnostic sont saisis librement par le garagiste (pas de catalogue) ;
 * service/produit sont toujours issus des catalogues Services/Produits du
 * garage, avec libellé et prix figés au moment de l'ajout de la ligne.
 */
enum QuoteLineType: string
{
    case DiagnosisFee = 'diagnosis_fee';
    case Service = 'service';
    case Product = 'product';
}
