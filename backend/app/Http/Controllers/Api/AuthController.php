<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Connexion, déconnexion et profil de la personne connectée (#70, #71). On s'authentifie
 * par jeton Sanctum : il n'y a pas de session côté serveur.
 */
class AuthController extends Controller
{
    /**
     * On connecte par e-mail et mot de passe. On renvoie un jeton Sanctum, que le front
     * stocke et renvoie dans l'en-tête Authorization.
     *
     * @throws ValidationException si les identifiants sont faux ou le compte désactivé
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            // On donne le même message dans les deux cas, pour ne pas révéler quelles
            // adresses existent dans la base.
            throw ValidationException::withMessages([
                'email' => "L'adresse e-mail ou le mot de passe est incorrect.",
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => "Ce compte a été désactivé. Contactez l'administratrice.",
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($request->string('device_name', 'api'));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * On déconnecte en supprimant uniquement le jeton utilisé, pour ne pas déconnecter
     * l'utilisateur de ses autres appareils.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /**
     * On renvoie le compte connecté : le front s'en sert au chargement pour retrouver la session.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * On change son mot de passe depuis son profil (#71). On déconnecte les autres appareils ;
     * la session en cours reste ouverte.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Mot de passe changé. Vos autres appareils sont déconnectés.']);
    }

    /**
     * Ce qu'on expose d'un compte au front.
     *
     * @return array{id: int, name: string, email: string, role: string, role_label: string, phone: string|null, last_login_at: string|null}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            // On affiche ces deux champs sur le profil (#71).
            'phone' => $user->phone,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
