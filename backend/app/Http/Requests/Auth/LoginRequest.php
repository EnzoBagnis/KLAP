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

    public function attributes(): array
    {
        return [
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }
}
