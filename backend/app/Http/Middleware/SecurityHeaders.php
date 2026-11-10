<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses du backend (#200, audit de sécurité).
 *
 * Le backend ne sert que du JSON et le pré-bilan Word : la politique de contenu interdit tout
 * (aucun script, aucune image, aucune intégration dans un cadre). Les en-têtes du frontend, lui,
 * relèvent de l'hébergement (voir docs/AUDIT_SECURITE.md).
 */
class SecurityHeaders
{
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; form-action 'none'; base-uri 'none'",
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /** Politique de la page de l'application (index.html servi par le backend, #237). */
    public const APP_CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
        ."font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            // La page de l'application garde sa propre politique de contenu.
            if ($name === 'Content-Security-Policy' && $response->headers->has($name)) {
                continue;
            }
            $response->headers->set($name, $value);
        }

        // HTTPS imposé au navigateur pour un an, seulement quand la requête arrive déjà en HTTPS.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // La version de PHP n'a pas à être annoncée (X-Powered-By, ajouté par PHP lui-même).
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
