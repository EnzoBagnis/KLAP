<?php

use Illuminate\Support\Facades\Route;

/*
 * Le backend n'a pas de pages : l'application est le frontend React. La racine répond en JSON,
 * sans session ni cookie (#200) : la page d'accueil Laravel posait deux cookies inutiles.
 */
Route::get('/', fn () => response()->json(['application' => 'KLAP', 'api' => url('/api')]))
    ->withoutMiddleware('web');
