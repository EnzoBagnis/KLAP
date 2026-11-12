<?php

namespace App\Http\Requests\Invitations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Activer son compte : on ne choisit que le mot de passe ; le nom et l'adresse viennent de
 * l'invitation (contexte client). On a repris les règles et les messages de la branche de
 * Lucas (#121).
 */
class ActivateInvitationRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', 'max:255', Password::min(8)->mixedCase()->numbers()->symbols()],
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
            'password.required' => 'Choisissez un mot de passe.',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'password.mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
            'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'password.symbols' => 'Le mot de passe doit contenir au moins un caractère spécial (@, #, !, etc.).',
            'password.confirmed' => 'La confirmation ne correspond pas au mot de passe.',
        ];
    }
}
