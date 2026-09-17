<?php

namespace App\Enums;

/**
 * Liste fixe des départements du Bénin, un des deux champs structurés de
 * localisation d'un profil Garage/Market Space (CLAUDE.md §5, ajout v0.17)
 * — données agrégées pour les autorités béninoises (CLAUDE.md §1). Comme
 * ServiceCategory, un professionnel choisit dans cette liste, pas de texte
 * libre ; l'extension à d'autres pays d'Afrique de l'Ouest (CLAUDE.md §1)
 * passera par l'ajout de nouveaux cas, un changement de code.
 */
enum Region: string
{
    case Alibori = 'alibori';
    case Atacora = 'atacora';
    case Atlantique = 'atlantique';
    case Borgou = 'borgou';
    case Collines = 'collines';
    case Couffo = 'couffo';
    case Donga = 'donga';
    case Littoral = 'littoral';
    case Mono = 'mono';
    case Oueme = 'oueme';
    case Plateau = 'plateau';
    case Zou = 'zou';

    public function label(): string
    {
        return match ($this) {
            self::Alibori => 'Alibori',
            self::Atacora => 'Atacora',
            self::Atlantique => 'Atlantique',
            self::Borgou => 'Borgou',
            self::Collines => 'Collines',
            self::Couffo => 'Couffo',
            self::Donga => 'Donga',
            self::Littoral => 'Littoral',
            self::Mono => 'Mono',
            self::Oueme => 'Ouémé',
            self::Plateau => 'Plateau',
            self::Zou => 'Zou',
        };
    }
}
