<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de lien « mot de passe oublié » (#81) : on ne demande que l'adresse.
 */
class ForgotPasswordRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:190']];
    }

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indiquez votre adresse mail.',
            'email.*' => 'L’adresse mail n’est pas valide.',
        ];
    }
}
