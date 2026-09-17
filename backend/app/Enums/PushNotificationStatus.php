<?php

namespace App\Enums;

/**
 * "Created" est à la fois le statut initial et, tant que FCM n'est pas
 * configuré, le statut final "en attente d'envoi réel" (CLAUDE.md §5, ajout
 * v0.12 ; §7, point ouvert) — aucune tentative d'envoi bloquante n'est faite
 * sans configuration FCM. "Sent"/"Failed" ne sont atteints qu'après une
 * tentative d'envoi réel.
 */
enum PushNotificationStatus: string
{
    case Created = 'created';
    case Sent = 'sent';
    case Failed = 'failed';
}
