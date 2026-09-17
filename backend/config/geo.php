<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuseau horaire métier
    |--------------------------------------------------------------------------
    |
    | Utilisé pour interpréter les horaires d'ouverture (GarageOpeningHour /
    | MarketSpaceOpeningHour, saisis en heure locale sans fuseau) et calculer
    | le statut "ouvert maintenant" (CLAUDE.md §5, ajout v0.13). Bénin
    | uniquement en V1 (Africa/Porto-Novo, UTC+1, pas d'heure d'été) — une
    | extension à d'autres pays d'Afrique de l'Ouest à fuseau différent (§1)
    | nécessitera de stocker le fuseau par garage/boutique plutôt qu'une
    | valeur globale (CLAUDE.md §7, point ouvert).
    |
    */

    'business_timezone' => env('GEO_BUSINESS_TIMEZONE', 'Africa/Porto-Novo'),

    /*
    |--------------------------------------------------------------------------
    | Rayon de recherche géolocalisée
    |--------------------------------------------------------------------------
    |
    | Rayon appliqué par défaut quand l'automobiliste n'en précise pas, et
    | plafond appliqué même si un rayon plus grand est demandé — évite des
    | résultats à des centaines de kilomètres (CLAUDE.md §5, ajout v0.13).
    |
    */

    'default_search_radius_km' => (float) env('GEO_DEFAULT_SEARCH_RADIUS_KM', 15),

    'max_search_radius_km' => (float) env('GEO_MAX_SEARCH_RADIUS_KM', 100),

];
