<?php

namespace App\Http\Requests\Logbook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la demande de déverrouillage d'une fiche de séance passée (J+10).
 * Conforme aux spécifications de Mariette Travard :
 * L'artiste doit obligatoirement justifier pourquoi il souhaite rouvrir la fiche en modification.
 */
class RequestUnlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif de la demande de déverrouillage est obligatoire.',
            'reason.min' => 'Le motif doit comporter au moins 10 caractères pour permettre à l’administration d’évaluer la demande.',
            'reason.max' => 'Le motif ne peut pas excéder 500 caractères.',
        ];
    }
}
