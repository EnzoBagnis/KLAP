<?php

use Illuminate\Support\Facades\Schedule;

// On verrouille les fiches à J+10 chaque nuit (#109). En production, il faut un cron qui
// lance `php artisan schedule:run` chaque minute (#79).
Schedule::command('logbook:lock-expired')->dailyAt('00:05')->timezone('Europe/Paris');

// Chaque nuit, on supprime de la base les jetons expirés depuis plus d'un jour (#157).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('00:15')->timezone('Europe/Paris');
