<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Demande de lien « mot de passe oublié » (#81) : l'adresse seulement. */
class ForgotPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:190']];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Indiquez votre adresse mail.',
            'email.*' => 'L’adresse mail n’est pas valide.',
        ];
    }
}
