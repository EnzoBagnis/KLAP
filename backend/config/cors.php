<?php

/*
 * CORS de l'API. Valeurs par défaut de Laravel 12, à deux exceptions près, pour que le
 * frontend (autre origine : localhost:3000 ou :5173) lise ces en-têtes :
 * - Retry-After : le délai d'attente d'une réponse 429, annoncé à l'utilisateur ;
 * - Content-Disposition : le nom du fichier du pré-bilan Word (#144).
 *
 * L'authentification se fait par jeton Bearer, sans cookie : supports_credentials
 * reste à false, et l'origine '*' est donc acceptable. À restreindre au domaine de
 * production lors du déploiement.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Origines autorisées, séparées par des virgules (#200). Par défaut toutes : le jeton Bearer
    // n'est jamais envoyé automatiquement par le navigateur, le risque reste faible. En production,
    // mettre l'adresse du frontend : CORS_ALLOWED_ORIGINS=https://klap.example.org
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
