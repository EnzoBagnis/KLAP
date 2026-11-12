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
 * Mot de passe oublié (#81), routes publiques. On répond toujours la même chose à une
 * demande, que l'adresse existe ou non : on ne révèle pas quels comptes existent. Un compte
 * désactivé ne reçoit rien. Après un nouveau mot de passe, on ferme toutes les sessions ouvertes.
 */
class PasswordResetController extends Controller
{
    /** La réponse unique à toute demande, que le compte existe ou non. */
    public const SENT = 'Si un compte actif correspond à cette adresse, un mail vient de lui être envoyé. Pensez à regarder dans les indésirables.';

    /**
     * On envoie le lien de réinitialisation, si un compte actif correspond à l'adresse.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $minutes = (int) config('auth.passwords.users.expire');

        // Le broker aligne toutes les réponses sur une même durée (auth.timebox_duration), et on
        // envoie le mail après la réponse (defer). Sans cela, un compte existant répondrait plus
        // lentement, et on pourrait deviner quelles adresses existent.
        Password::sendResetLink(['email' => $request->validated('email'), 'is_active' => true], function (User $user, string $token) use ($minutes) {
            $url = rtrim(config('app.frontend_url'), '/').'/reinitialisation/'.$token.'?email='.rawurlencode($user->email);
            defer(function () use ($user, $url, $minutes) {
                try {
                    Mail::to($user->email)->send(new PasswordResetMail($user, $url, $minutes));
                } catch (Throwable $exception) {
                    // La réponse est déjà partie, identique à un envoi réussi : on journalise l'échec.
                    Log::error('Mail de mot de passe oublié non envoyé', ['user_id' => $user->id, 'error' => $exception::class]);
                }
            });
        });

        return response()->json(['message' => self::SENT]);
    }

    /**
     * On enregistre le nouveau mot de passe à partir du lien reçu par mail, puis on ferme
     * toutes les sessions du compte.
     *
     * @throws ValidationException si le lien est expiré, déjà utilisé ou faux
     */
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
