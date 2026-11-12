<?php

namespace App\Http\Requests\Projects;

use App\Rules\UniqueProjectTypeName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renommer un type de projet, le désactiver ou le réactiver.
 */
class UpdateProjectTypeRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                new UniqueProjectTypeName($this->route('projectType')?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
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
            'is_active.boolean' => 'L’état actif doit valoir vrai ou faux.',
        ];
    }
}
