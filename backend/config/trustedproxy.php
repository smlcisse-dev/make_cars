<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiance
    |--------------------------------------------------------------------------
    |
    | Derrière un proxy ou un répartiteur de charge (Nginx d'un hébergeur,
    | Cloudflare, load balancer…), chaque requête arrive depuis l'adresse du
    | proxy, qui transmet l'adresse réelle du visiteur dans l'en-tête
    | X-Forwarded-For. Laravel n'accepte cet en-tête que s'il vient d'un proxy
    | déclaré ici ; sinon $request->ip() renvoie l'adresse du proxy pour tous
    | les visiteurs, et les limites de débit par IP (connexion : 20 par minute,
    | voir AppServiceProvider::configureRateLimiting) bloqueraient tout le
    | monde à la fois.
    |
    | Lu par le middleware TrustProxies du framework. Valeurs de
    | TRUSTED_PROXIES :
    | - vide (défaut, développement) : aucun proxy, l'en-tête est ignoré ;
    | - adresses ou plages séparées par des virgules, celles que l'hébergeur
    |   documente pour ses proxies (ex. "10.0.0.0/8,172.16.0.0/12") ;
    | - "*" : faire confiance à l'appelant direct, seulement si le serveur
    |   n'est joignable QUE par le proxy (sinon un visiteur pourrait choisir
    |   son adresse en envoyant lui-même l'en-tête).
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
