<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\ActivateInvitationRequest;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\KlapNotification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Invitations (#126), deuxième partie du portage de #121. L'administratrice les crée ; on
 * les lit et on les active sans être connecté, avec le jeton du mail. Les réponses publiques
 * ne parlent que du jeton, jamais de l'existence d'un compte.
 */
class InvitationController extends Controller
{
    /**
     * On crée une invitation et on envoie son mail. Si le mail ne part pas, on ne garde rien
     * et on répond 503.
     */
    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $token = Str::random(64);

        try {
            $invitation = DB::transaction(function () use ($request, $token) {
                // On verrouille le compte de l'administratrice : deux invitations envoyées en même
                // temps à la même adresse (double appui) passent l'une après l'autre, au lieu de
                // se bloquer mutuellement en base (erreur 500).
                User::query()->whereKey($request->user()->id)->lockForUpdate()->first();
                // On remplace l'invitation en cours pour cette adresse : son ancien lien ne sert plus.
                $replaced = Invitation::query()->pending()->where('email', $request->validated('email'))
                    ->update(['expires_at' => now()]) > 0;
                $invitation = Invitation::query()->create([
                    ...$request->validated(),
                    'token_hash' => Invitation::hashToken($token),
                    'invited_by' => $request->user()->id,
                    'sent_at' => now(),
                    'expires_at' => now()->addDays(Invitation::VALID_DAYS),
                ]);
                // On envoie dans la transaction : un mail qui ne part pas ne laisse pas d'invitation.
                Mail::to($invitation->email)->send(new InvitationMail(
                    $invitation->load('inviter'),
                    rtrim(config('app.frontend_url'), '/').'/activation/'.$token,
                ));

                $invitation->replaced = $replaced;

                return $invitation;
            });
        } catch (Throwable $exception) {
            // On ne transforme en 503 qu'un échec d'envoi ; une erreur de base reste une erreur de base.
            if ($exception instanceof ValidationException || $exception instanceof QueryException) {
                throw $exception;
            }
            Log::error('Invitation non envoyée', ['email' => $request->validated('email'), 'error' => $exception->getMessage()]);
            abort(503, 'Le mail d’invitation n’a pas pu être envoyé. Réessayez dans un instant.');
        }

        return response()->json(['data' => [...$this->summary($invitation), 'replaced' => $invitation->replaced]], 201);
    }

    /**
     * On montre à la personne invitée ce que contient son invitation, avant qu'elle choisisse
     * son mot de passe.
     *
     * @param  string  $token  le jeton du lien reçu par mail
     */
    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->summary($this->usable($token))]);
    }

    /**
     * On crée le compte à partir de l'invitation, puis on prévient l'administratrice qui
     * l'avait envoyée.
     *
     * @param  string  $token  le jeton du lien reçu par mail
     */
    public function activate(ActivateInvitationRequest $request, string $token): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request, $token) {
                // On verrouille la ligne : un double clic ne crée pas deux comptes.
                $invitation = $this->usable($token, lock: true);
                abort_if(User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists(), 409,
                    'Un compte existe déjà pour cette invitation. Connectez-vous avec votre adresse.');

                $user = User::query()->create([
                    'name' => $invitation->full_name,
                    'email' => $invitation->email,
                    'password' => Hash::make($request->validated('password')),
                    'role' => $invitation->role,
                    'is_active' => true,
                ]);
                $invitation->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();
                KlapNotification::query()->create([
                    'recipient_id' => $invitation->invited_by,
                    'type' => 'invitation_accepted',
                    'message' => mb_strimwidth("{$invitation->full_name} a activé son compte.", 0, 255, '…'),
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // C'est notre filet si deux activations passent malgré tout (SQLite ne verrouille pas).
            abort(409, 'Un compte existe déjà pour cette invitation. Connectez-vous avec votre adresse.');
        }

        return response()->json(['data' => ['email' => $user->email, 'name' => $user->name]], 201);
    }

    /**
     * On retrouve l'invitation encore utilisable pour ce jeton : 404 s'il est inconnu, 410 si
     * l'invitation a expiré ou a déjà servi.
     *
     * @param  bool  $lock  verrouiller la ligne, le temps de créer le compte
     */
    private function usable(string $token, bool $lock = false): Invitation
    {
        $invitation = strlen($token) === 64
            ? Invitation::query()->when($lock, fn ($query) => $query->lockForUpdate())
                ->where('token_hash', Invitation::hashToken($token))->first()
            : null;
        abort_if($invitation === null, 404, 'Ce lien d’invitation n’existe pas. Vérifiez le lien reçu par mail.');
        abort_if($invitation->accepted_at !== null, 410, 'Ce lien a déjà servi : votre compte est activé, connectez-vous.');
        abort_if(! $invitation->isUsable(), 410,
            'Ce lien a expiré. Demandez une nouvelle invitation à l’administratrice.');

        return $invitation;
    }

    /**
     * Ce qu'on expose d'une invitation.
     *
     * @return array{full_name: string, email: string, role: string, expires_at: string}
     */
    private function summary(Invitation $invitation): array
    {
        return [
            'full_name' => $invitation->full_name,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }
}
