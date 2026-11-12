<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On restreint une route à certains rôles : ->middleware('role:admin')
 * ou ->middleware('role:admin,super_user').
 */
class EnsureUserHasRole
{
    /**
     * On laisse passer la requête si le compte connecté a l'un des rôles demandés ; sinon 401
     * (pas connecté) ou 403 (pas le bon rôle).
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  les valeurs de UserRole acceptées
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403, "Vous n'avez pas les droits pour cette action.");
        }

        return $next($request);
    }
}
