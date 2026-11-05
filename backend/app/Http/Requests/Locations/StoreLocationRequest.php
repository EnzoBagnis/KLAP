<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'un lieu d'atelier / représentation.
 * Conforme aux exigences de Mariette Travard :
 * Champs obligatoires : Nom du lieu, Code postal (5 chiffres), Ville.
 */
class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->is_active;
    }

    /**
     * Nettoie les espaces multiples internes avant validation.
     */
    protected function prepareForValidation(): void
    {
        $merges = [];
        if ($this->has('name')) {
            $merges['name'] = preg_replace('/\s+/', ' ', trim((string) $this->input('name')));
        }
        if ($this->has('city')) {
            $merges['city'] = preg_replace('/\s+/', ' ', trim((string) $this->input('city')));
        }
        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'structure' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_klap' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du lieu est obligatoire.',
            'name.max' => 'Le nom du lieu ne peut pas dépasser 150 caractères.',
            'postal_code.required' => 'Le code postal est obligatoire.',
            'postal_code.regex' => 'Le code postal doit être composé de 5 chiffres (ex: 13003).',
            'city.required' => 'La ville est obligatoire.',
            'city.max' => 'La ville ne peut pas dépasser 100 caractères.',
        ];
    }
}
