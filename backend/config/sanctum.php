<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    // Domaines autorisés pour les sessions SPA (React en local et Alwaysdata en prod)
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    // Garde d'authentification par défaut
    'guard' => ['web'],

    // Durée de validité des tokens en minutes (null = pas d'expiration automatique)
    'expiration' => null,

    // Préfixe optionnel des tokens
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    // Middlewares utilisés par Sanctum pour sécuriser les requêtes
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
