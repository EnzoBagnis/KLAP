<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouveau lieu (#134), ouvert à tous les rôles : « chacun peut créer un nouveau lieu (projets
 * en tournée) ; champs obligatoires : nom, code postal, ville » (contexte client). Repris de la
 * branche de Lucas (#121), sans la colonne `structure` ni `is_klap`.
 */
class StoreLocationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tidy = fn ($value) => is_string($value) ? preg_replace('/\s+/u', ' ', trim($value)) : $value;
        $this->merge(collect(['name', 'city', 'address', 'postal_code'])
            ->filter(fn ($key) => $this->has($key))
            ->mapWithKeys(fn ($key) => [$key => $tidy($this->input($key))])
            ->all());
    }

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

    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du lieu.',
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
