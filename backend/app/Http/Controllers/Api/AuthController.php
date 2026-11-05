<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateInvitationRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Invitation;
use App\Models\KlapNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Contrôleur d'authentification API pour KLAP.
 * Gère la connexion, déconnexion, consultation du profil connecté,
 * et l'activation des comptes sur invitation.
 */
class AuthController extends Controller
{
    /**
     * Connexion de l'utilisateur (avec rate limiting 5 tentatives/min).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = Auth::user();

        // Enregistre l'horodatage de la dernière connexion
        $user->update(['last_login_at' => Carbon::now()]);

        // Régénère la session pour contrer les attaques par fixation de session
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Génère un jeton d'API Sanctum (supporte à la fois les requêtes mobiles et le web SPA)
        $token = $user->createToken('klap_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Renvoie le profil de l'utilisateur actuellement connecté avec ses alertes.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $unreadCount = $user->klapNotifications()->unread()->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'unread_notifications_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Déconnexion sécurisée : révoque les tokens et détruit la session.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Récupère les détails d'une invitation par son token.
     */
    public function getInvitation(string $token): JsonResponse
    {
        $hash = hash('sha256', $token);
        $invitation = Invitation::where('token_hash', $hash)->first();

        if (! $invitation) {
            return response()->json(['message' => 'Lien d’invitation introuvable ou invalide.'], 404);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'Cette invitation a déjà été activée. Vous pouvez vous connecter directement avec votre adresse e-mail et votre mot de passe.',
            ], 410);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'Ce lien d’invitation a expiré (délai de 7 jours dépassé). Veuillez contacter Mariette Travard (administration KLAP) pour recevoir un nouveau lien.',
            ], 410);
        }

        return response()->json([
            'email' => $invitation->email,
            'full_name' => $invitation->full_name,
            'role' => $invitation->role,
        ]);
    }

    /**
     * Active le compte utilisateur via le lien d'invitation reçu.
     */
    public function activateInvitation(ActivateInvitationRequest $request, string $token): JsonResponse
    {
        $hash = hash('sha256', $token);
        $invitation = Invitation::where('token_hash', $hash)->first();

        if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            return response()->json(['message' => 'Invitation invalide ou expirée.'], 400);
        }

        $user = DB::transaction(function () use ($request, $invitation) {
            // Création du compte utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'role' => $invitation->role,
                'is_active' => true,
                'last_login_at' => Carbon::now(),
            ]);

            // Marque l'invitation comme acceptée
            $invitation->update([
                'user_id' => $user->id,
                'accepted_at' => Carbon::now(),
            ]);

            // Notifie l'administrateur
            KlapNotification::create([
                'recipient_id' => $invitation->invited_by,
                'type' => 'invitation_accepted',
                'message' => "{$user->name} a activé son compte ({$invitation->role}).",
            ]);

            return $user;
        });

        // Connecte automatiquement l'utilisateur et génère son token
        $authToken = $user->createToken('klap_token')->plainTextToken;

        return response()->json([
            'message' => 'Votre compte est activé avec succès. Bienvenue sur KLAP !',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $authToken,
        ], 201);
    }
}
