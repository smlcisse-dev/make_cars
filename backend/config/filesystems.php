<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disque des justificatifs KYC
    |--------------------------------------------------------------------------
    |
    | Disque privé utilisé pour les justificatifs d'inscription professionnelle
    | (registre de commerce, photos du local — CLAUDE.md §5 règle 4). "local"
    | par défaut en attendant les identifiants Supabase Storage ; bascule sur
    | "supabase" en changeant uniquement cette variable d'environnement.
    |
    */

    'kyc_documents_disk' => env('KYC_DOCUMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disque des médias publics
    |--------------------------------------------------------------------------
    |
    | Disque public utilisé pour les photos affichées côté app mobile (profil
    | garage, produits...). "public" par défaut ; bascule sur "supabase_public"
    | (bucket public distinct du bucket KYC privé) une fois configuré.
    |
    */

    'public_media_disk' => env('PUBLIC_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Disque des documents privés (chat, devis/factures)
    |--------------------------------------------------------------------------
    |
    | Disque privé pour du contenu propre à une relation précise (pièces
    | jointes du chat, PDF de devis/facture) — jamais d'URL publique, toujours
    | téléchargé via un endpoint authentifié qui vérifie l'appartenance
    | (mêmes principes que kyc_documents_disk, mais sans rapport avec le KYC :
    | clé distincte pour ne pas mélanger deux natures de documents dans un
    | même bucket). "local" par défaut ; bascule sur "supabase" une fois
    | configuré (peut réutiliser le même bucket privé que le KYC via des
    | préfixes de chemin différents, ou un bucket dédié — au choix en prod).
    |
    */

    'private_media_disk' => env('PRIVATE_MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Supabase Storage expose une API compatible S3 (Project Settings >
        // Storage > S3 Connection dans le dashboard Supabase). Bucket privé
        // pour les justificatifs KYC.
        'supabase' => [
            'driver' => 's3',
            'key' => env('SUPABASE_STORAGE_KEY'),
            'secret' => env('SUPABASE_STORAGE_SECRET'),
            'region' => env('SUPABASE_STORAGE_REGION', 'eu-central-1'),
            'bucket' => env('SUPABASE_STORAGE_BUCKET'),
            'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

        // Même compte Supabase, bucket public distinct pour les médias
        // affichés côté app mobile (photos de garage, de produits...).
        'supabase_public' => [
            'driver' => 's3',
            'key' => env('SUPABASE_STORAGE_KEY'),
            'secret' => env('SUPABASE_STORAGE_SECRET'),
            'region' => env('SUPABASE_STORAGE_REGION', 'eu-central-1'),
            'bucket' => env('SUPABASE_PUBLIC_BUCKET'),
            'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),
            'url' => env('SUPABASE_PUBLIC_URL'),
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
