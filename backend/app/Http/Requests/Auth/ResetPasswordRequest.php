<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Invitations\ActivateInvitationRequest;
use Illuminate\Foundation\Http\FormRequest;

/** Nouveau mot de passe (#81) : mêmes règles et messages qu'à l'activation d'un compte. */
class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:190'],
            ...(new ActivateInvitationRequest)->rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'token.*' => 'Ce lien n’est pas valable : demandez-en un nouveau.',
            'email.*' => 'Ce lien n’est pas valable : demandez-en un nouveau.',
            ...(new ActivateInvitationRequest)->messages(),
        ];
    }
}
