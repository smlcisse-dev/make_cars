<?php

/**
 * Inscription professionnelle en deux temps (CLAUDE.md §5, ajout v0.26) :
 * vérification de l'email par code avant toute création de compte. Les
 * règles du code lui-même (longueur, validité, essais, délai entre deux
 * envois) sont communes avec le mot de passe oublié : config/email_codes.php.
 */
return [

    // Durée de vie d'une demande non vérifiée, en heures : au-delà, elle est
    // purgée au fil de l'eau (aucune tâche planifiée ne tourne).
    'pending_lifetime_hours' => 24,

];
