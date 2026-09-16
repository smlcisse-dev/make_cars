<?php

namespace App\Enums;

/**
 * Même document, mention différente selon le moment du cycle de vie
 * (CLAUDE.md §5, ajout v0.8) : "Devis" tant que le paiement n'est pas
 * enregistré, "Facture" une fois marqué payé.
 */
enum QuoteDocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
}
