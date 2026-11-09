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

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
