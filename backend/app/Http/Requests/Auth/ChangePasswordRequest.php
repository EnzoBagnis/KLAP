<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Invitations\ActivateInvitationRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changer son mot de passe depuis son profil (#71) : on demande l'actuel, puis le nouveau,
 * avec les mêmes règles qu'à l'activation d'un compte.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            ...(new ActivateInvitationRequest)->rules(),
            'password' => [...(new ActivateInvitationRequest)->rules()['password'], 'different:current_password'],
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
            'current_password.required' => 'Saisissez votre mot de passe actuel.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
            ...(new ActivateInvitationRequest)->messages(),
        ];
    }
}
