<?php

/**
 * Inscription professionnelle en deux temps (CLAUDE.md §5, ajout v0.26) :
 * vérification de l'email par code avant toute création de compte.
 */
return [

    // Nombre de chiffres du code envoyé par email (zéros en tête conservés).
    'code_length' => 6,

    // Durée de validité d'un code, en minutes.
    'code_ttl_minutes' => 15,

    // Nombre d'essais autorisés par code avant verrouillage.
    'max_attempts' => 5,

    // Délai minimum entre deux envois de code, en secondes.
    'resend_cooldown_seconds' => 60,

    // Durée de vie d'une demande non vérifiée, en heures : au-delà, elle est
    // purgée au fil de l'eau (aucune tâche planifiée ne tourne).
    'pending_lifetime_hours' => 24,

];
