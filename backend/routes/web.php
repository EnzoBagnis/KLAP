<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

/*
 * Le frontend React est servi par le backend (#79) : l'accueil et toutes les pages de
 * React Router renvoient index.html. Sauf sous /api : une adresse d'API inconnue doit rester
 * un 404 JSON (#237), et non la page de l'application avec un code 200.
 * Sans session ni cookie (#200) : ces pages n'en ont pas besoin.
 */
Route::get('/{page?}', function () {
    $index = public_path('index.html');

    // La politique de l'API (default-src 'none') bloquerait les scripts et le CSS de la page :
    // index.html reçoit celle du frontend (frontend/build/htaccess.js), l'API étant sur le même site.
    return file_exists($index)
        ? response()->file($index, ['Content-Security-Policy' => SecurityHeaders::APP_CSP, 'Cache-Control' => 'no-cache'])
        : response()->json(['application' => 'KLAP', 'api' => url('/api')]);
})->where('page', '(?!api(?:/|$)).*')->withoutMiddleware('web');
