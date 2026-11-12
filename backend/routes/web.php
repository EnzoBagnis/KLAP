<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

/*
 * C'est le backend qui sert le frontend React (#79) : pour l'accueil et toutes les pages de
 * React Router, on renvoie index.html. Sauf sous /api : une adresse d'API inconnue doit rester
 * un 404 JSON (#237), et non la page de l'application avec un code 200.
 * On retire le groupe « web » (session, cookies) : ces pages n'en ont pas besoin (#200).
 */
Route::get('/{page?}', function () {
    $index = public_path('index.html');

    // La politique de l'API (default-src 'none') bloquerait les scripts et le CSS de la page :
    // on donne à index.html celle du frontend (frontend/build/htaccess.js), puisque l'API est
    // sur le même site.
    return file_exists($index)
        ? response()->file($index, ['Content-Security-Policy' => SecurityHeaders::APP_CSP, 'Cache-Control' => 'no-cache'])
        : response()->json(['application' => 'KLAP', 'api' => url('/api')]);
})->where('page', '(?!api(?:/|$)).*')->withoutMiddleware('web');
