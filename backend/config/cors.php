<?php

/*
 * CORS de l'API. Valeurs par défaut de Laravel 12, à une exception près :
 * Retry-After est exposé, pour que le frontend (autre origine : localhost:3000 ou :5173)
 * puisse lire le délai d'attente d'une réponse 429 et l'annoncer à l'utilisateur.
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

    'exposed_headers' => ['Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
