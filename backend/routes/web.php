<?php

use Illuminate\Support\Facades\Route;

// Sert l'application React pour l'accueil et toutes les pages (React Router)
Route::fallback(function () {
    if (file_exists(public_path('index.html'))) {
        return response()->file(public_path('index.html'));
    }
    return response()->json(['application' => 'KLAP', 'api' => url('/api')]);
});
