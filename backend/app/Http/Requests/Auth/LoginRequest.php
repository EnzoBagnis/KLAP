<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            // Nom de l'appareil, affiché plus tard dans le profil ("Chrome du lycée").
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /** En français : l'écran de connexion affiche tel quel le message de l'API. */
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

    public function attributes(): array
    {
        return [
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }
}
