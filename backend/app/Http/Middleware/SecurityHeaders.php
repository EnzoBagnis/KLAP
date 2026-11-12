<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses du backend (#200, audit de sécurité).
 *
 * L'API ne sert que du JSON, le pré-bilan Word et l'export Excel : pour elle, la politique
 * de contenu interdit tout (aucun script, aucune image, aucune intégration dans un cadre).
 * Quand c'est le backend qui sert la page de l'application (#237), on lui applique APP_CSP.
 */
class SecurityHeaders
{
    /** Les en-têtes qu'on pose sur toutes les réponses. */
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; form-action 'none'; base-uri 'none'",
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /** La politique de contenu de la page de l'application (index.html servi par le backend, #237). */
    public const APP_CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
        ."font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    /**
     * On ajoute les en-têtes de sécurité à la réponse.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            // Si la réponse a déjà sa politique de contenu, on la lui laisse.
            if ($name === 'Content-Security-Policy' && $response->headers->has($name)) {
                continue;
            }
            $response->headers->set($name, $value);
        }

        // Hors de l'API, on sert la page React : sa politique doit autoriser ses scripts, ses
        // styles, ses polices et ses images.
        if (! $request->is('api', 'api/*')) {
            $response->headers->set('Content-Security-Policy', self::APP_CSP);
        }

        // On impose HTTPS au navigateur pour un an, seulement quand la requête arrive déjà en HTTPS.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // On n'annonce pas la version de PHP (X-Powered-By, que PHP ajoute de lui-même).
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
