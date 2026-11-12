<?php

namespace App\Http\Requests\Projects;

use App\Rules\UniqueProjectTypeName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nouveau type de projet : un nom, unique sans tenir compte de la casse.
 */
class StoreProjectTypeRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new UniqueProjectTypeName],
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
            'name.required' => 'Le nom du type de projet est obligatoire.',
            'name.string' => 'Le nom du type de projet doit être un texte.',
            'name.max' => 'Le nom du type de projet ne doit pas dépasser :max caractères.',
        ];
    }
}
