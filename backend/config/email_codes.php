<?php

/**
 * Codes à usage unique envoyés par email : vérification de l'email à
 * l'inscription professionnelle (CLAUDE.md §5, ajout v0.26) et mot de passe
 * oublié (ajout v0.29). Mêmes règles pour les deux parcours, qui lisent
 * toutes ces valeurs ici.
 */
return [

    // Nombre de chiffres du code (zéros en tête conservés).
    'length' => 6,

    // Durée de validité d'un code, en minutes.
    'ttl_minutes' => 15,

    // Nombre d'essais autorisés par code avant verrouillage.
    'max_attempts' => 5,

    // Délai minimum entre deux envois de code, en secondes.
    'resend_cooldown_seconds' => 60,

];
