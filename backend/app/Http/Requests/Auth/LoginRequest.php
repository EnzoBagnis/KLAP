<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connexion : une adresse et un mot de passe.
 */
class LoginRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            // Le nom de l'appareil, qu'on donne au jeton Sanctum (« Chrome du lycée »).
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * Les messages d'erreur, en français : l'écran de connexion affiche tel quel le message
     * de l'API.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.*' => 'Cette adresse e-mail n’est pas valide.',
            'password.required' => 'Saisissez votre mot de passe.',
            'password.*' => 'Le mot de passe n’est pas valide.',
            'device_name.*' => 'Le nom de l’appareil n’est pas valide.',
        ];
    }

    /**
     * Les noms des champs, tels qu'on les écrit dans les messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }
}
