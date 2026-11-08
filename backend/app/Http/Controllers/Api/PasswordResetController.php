<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Mot de passe oublié (#81), routes publiques. La réponse à une demande est toujours la même,
 * que l'adresse existe ou non : elle ne révèle pas quels comptes existent. Un compte désactivé
 * ne reçoit rien. Après un nouveau mot de passe, toutes les sessions ouvertes sont fermées.
 */
class PasswordResetController extends Controller
{
    public const SENT = 'Si un compte actif correspond à cette adresse, un mail vient de lui être envoyé. Pensez à regarder dans les indésirables.';

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $minutes = (int) config('auth.passwords.users.expire');

        // Le broker aligne toutes les réponses sur une même durée (auth.timebox_duration) ; le mail
        // part après la réponse (defer). Sans cela, un compte existant répondrait plus lentement.
        Password::sendResetLink(['email' => $request->validated('email'), 'is_active' => true], function (User $user, string $token) use ($minutes) {
            $url = rtrim(config('app.frontend_url'), '/').'/reinitialisation/'.$token.'?email='.rawurlencode($user->email);
            defer(function () use ($user, $url, $minutes) {
                try {
                    Mail::to($user->email)->send(new PasswordResetMail($user, $url, $minutes));
                } catch (Throwable $exception) {
                    // La réponse est déjà partie, identique à un envoi réussi : l'échec est journalisé.
                    Log::error('Mail de mot de passe oublié non envoyé', ['user_id' => $user->id, 'error' => $exception::class]);
                }
            });
        });

        return response()->json(['message' => self::SENT]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), 'is_active' => true],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Ce lien n’est plus valable : demandez-en un nouveau.']);
        }

        return response()->json(['message' => 'Votre mot de passe est changé. Connectez-vous avec le nouveau.']);
    }
}
