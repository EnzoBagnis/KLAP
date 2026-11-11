<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verrouillage des fiches à J+10 (#109). En production, cron : `php artisan schedule:run` chaque minute (#79).
Schedule::command('logbook:lock-expired')->dailyAt('00:05')->timezone('Europe/Paris');

// Jetons expirés depuis plus d'un jour supprimés de la base chaque nuit (#157).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('00:15')->timezone('Europe/Paris');
