<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Invitations\ActivateInvitationRequest;
use Illuminate\Foundation\Http\FormRequest;

/** Changer son mot de passe depuis son profil (#71) : l'actuel, puis le nouveau (règles de l'activation). */
class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            ...(new ActivateInvitationRequest)->rules(),
            'password' => [...(new ActivateInvitationRequest)->rules()['password'], 'different:current_password'],
        ];
    }

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
