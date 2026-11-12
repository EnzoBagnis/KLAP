<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Invitations\ActivateInvitationRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nouveau mot de passe (#81) : on reprend les règles et les messages de l'activation d'un compte.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:190'],
            ...(new ActivateInvitationRequest)->rules(),
        ];
    }

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'token.*' => 'Ce lien n’est pas valable : demandez-en un nouveau.',
            'email.*' => 'Ce lien n’est pas valable : demandez-en un nouveau.',
            ...(new ActivateInvitationRequest)->messages(),
        ];
    }
}
