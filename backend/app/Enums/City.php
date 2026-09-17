<?php

namespace App\Enums;

/**
 * Liste fixe des villes principales du Bénin, second champ structuré de
 * localisation d'un profil Garage/Market Space, indépendant du département
 * (CLAUDE.md §5, ajout v0.17). Même principe que Region : liste figée par la
 * plateforme, extensible par changement de code (autres pays d'Afrique de
 * l'Ouest, CLAUDE.md §1).
 */
enum City: string
{
    case Cotonou = 'cotonou';
    case PortoNovo = 'porto_novo';
    case Parakou = 'parakou';
    case AbomeyCalavi = 'abomey_calavi';
    case Bohicon = 'bohicon';
    case Abomey = 'abomey';
    case Natitingou = 'natitingou';
    case Djougou = 'djougou';
    case Lokossa = 'lokossa';
    case Kandi = 'kandi';
    case Ouidah = 'ouidah';
    case Savalou = 'savalou';
    case Save = 'save';
    case DassaZoume = 'dassa_zoume';
    case Aplahoue = 'aplahoue';
    case Come = 'come';
    case Dogbo = 'dogbo';
    case Malanville = 'malanville';
    case Nikki = 'nikki';
    case Tanguieta = 'tanguieta';
    case Pobe = 'pobe';
    case Sakete = 'sakete';
    case Ouinhi = 'ouinhi';
    case Athieme = 'athieme';

    public function label(): string
    {
        return match ($this) {
            self::Cotonou => 'Cotonou',
            self::PortoNovo => 'Porto-Novo',
            self::Parakou => 'Parakou',
            self::AbomeyCalavi => 'Abomey-Calavi',
            self::Bohicon => 'Bohicon',
            self::Abomey => 'Abomey',
            self::Natitingou => 'Natitingou',
            self::Djougou => 'Djougou',
            self::Lokossa => 'Lokossa',
            self::Kandi => 'Kandi',
            self::Ouidah => 'Ouidah',
            self::Savalou => 'Savalou',
            self::Save => 'Savè',
            self::DassaZoume => 'Dassa-Zoumè',
            self::Aplahoue => 'Aplahoué',
            self::Come => 'Comè',
            self::Dogbo => 'Dogbo',
            self::Malanville => 'Malanville',
            self::Nikki => 'Nikki',
            self::Tanguieta => 'Tanguiéta',
            self::Pobe => 'Pobè',
            self::Sakete => 'Sakété',
            self::Ouinhi => 'Ouinhi',
            self::Athieme => 'Athiémé',
        };
    }
}
