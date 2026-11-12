<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pour une adresse d'API inconnue (dont un identifiant non numérique, #200), on donne la
        // même réponse que pour un élément introuvable ou interdit : on ne révèle rien des
        // routes ni des données.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api', 'api/*') && $request->route() === null) {
                // On reprend les options d'encodage des 404 de Laravel : la réponse est
                // identique à l'octet près.
                return response()->json(['message' => ''], 404, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        });
    })->create();
