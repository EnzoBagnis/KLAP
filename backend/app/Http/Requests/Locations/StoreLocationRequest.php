<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouveau lieu (#134), ouvert à tous les rôles : « chacun peut créer un nouveau lieu (projets
 * en tournée) ; champs obligatoires : nom, code postal, ville » (contexte client). On est
 * repartis de la branche de Lucas (#121), sans la colonne `structure` ni `is_klap`.
 */
class StoreLocationRequest extends FormRequest
{
    /**
     * On nettoie les champs de texte avant de les valider.
     */
    protected function prepareForValidation(): void
    {
        // On réduit les espaces ; un champ qui se retrouve vide devient null (l'adresse est facultative).
        $tidy = function ($value) {
            if (! is_string($value)) {
                return $value;
            }
            $value = preg_replace('/\s+/u', ' ', trim($value));

            return $value === '' ? null : $value;
        };
        $this->merge(collect(['name', 'city', 'address', 'postal_code'])
            ->filter(fn ($key) => $this->has($key))
            ->mapWithKeys(fn ($key) => [$key => $tidy($this->input($key))])
            ->all());
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150',
                Rule::unique('locations')->where('postal_code', is_string($this->input('postal_code')) ? $this->input('postal_code') : '')],
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
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
            'name.required' => 'Indiquez le nom du lieu.',
            'name.string' => 'Le nom du lieu n’est pas valide.',
            'postal_code.string' => 'Le code postal doit compter 5 chiffres.',
            'city.string' => 'La ville n’est pas valide.',
            'address.string' => 'L’adresse n’est pas valide.',
            'name.max' => 'Le nom ne doit pas dépasser 150 caractères.',
            'name.unique' => 'Ce lieu existe déjà pour ce code postal.',
            'postal_code.required' => 'Indiquez le code postal.',
            'postal_code.regex' => 'Le code postal doit compter 5 chiffres.',
            'city.required' => 'Indiquez la ville.',
            'city.max' => 'La ville ne doit pas dépasser 100 caractères.',
            'address.max' => 'L’adresse ne doit pas dépasser 255 caractères.',
        ];
    }
}
