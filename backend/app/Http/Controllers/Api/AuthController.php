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

class AuthController extends Controller
{
    /**
     * Connexion par e-mail et mot de passe. Renvoie un token Sanctum
     * que le front stocke et renvoie dans l'en-tête Authorization.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            // Même message dans les deux cas, pour ne pas révéler quelles
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
     * Déconnexion : on supprime uniquement le token utilisé, pour ne pas
     * déconnecter l'utilisateur de ses autres appareils.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Changer son mot de passe depuis son profil (#71). Les autres appareils sont déconnectés ;
     * la session en cours reste ouverte.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Mot de passe changé. Vos autres appareils sont déconnectés.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            // Affichés sur le profil (#71).
            'phone' => $user->phone,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
