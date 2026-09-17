<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Client ID OAuth 2.0 de type "Web" créé dans Google Cloud Console —
     * sert d'audience (claim "aud") pour vérifier les ID tokens envoyés par
     * l'app Flutter (CLAUDE.md §5, ajout v0.5). Pas de client secret : aucun
     * échange de code d'autorisation côté serveur dans ce flux.
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    /*
     * Notifications push (CLAUDE.md §5, ajout v0.12). Tant que
     * FCM_SERVER_KEY est absent, PushNotificationService fonctionne en mode
     * simulation : les notifications sont créées et enregistrées en base,
     * jamais réellement envoyées, sans erreur bloquante (même approche que
     * le paiement manuel V1 — CLAUDE.md §7, point ouvert). Clé serveur de
     * l'API FCM historique (HTTP legacy) — une future migration vers l'API
     * HTTP v1 (OAuth, clé de compte de service) est un changement de
     * PushNotificationService::attemptDelivery() uniquement, sans impact sur
     * le reste du module.
     */
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

];
