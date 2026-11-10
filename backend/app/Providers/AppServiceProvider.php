<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Identifiants des routes : des chiffres seulement (#200). Sans cela, MySQL lit « 1abc »
        // comme 1 et /api/projects/1abc renvoie le projet 1 (sans fuite : les droits s'appliquent).
        Route::patterns(array_fill_keys(['account', 'entry', 'funder', 'location', 'notification', 'project', 'projectType', 'session', 'subProject', 'user'], '[0-9]+'));

        // Connexion (#150) : 5 essais par minute pour un compte depuis une IP (frein à l'essai de
        // mots de passe), 30 par IP en plafond, pour ne pas bloquer des collègues sur le même Wi-Fi.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('account|'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()),
            Limit::perMinute(30)->by('ip|'.$request->ip()),
        ]);

        // Un compte désactivé perd l'accès tout de suite, même avec un jeton créé avant sa
        // désactivation et quelle que soit la façon dont il a été désactivé (#148).
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid) => $isValid
                && $token->tokenable instanceof User
                && $token->tokenable->is_active
        );
    }
}
