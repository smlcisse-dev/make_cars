<?php

namespace App\Enums;

/**
 * Un avis est toujours visible publiquement à sa création (aucune validation
 * admin préalable, contrairement aux produits/services — CLAUDE.md §5, ajout
 * v0.10) ; seule une modération a posteriori peut le masquer. "Hidden" est un
 * masquage logique tracé (motif, auteur, date), jamais une suppression SQL,
 * pour garder la preuve de toute action de modération.
 */
enum ReviewStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
