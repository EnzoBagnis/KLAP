<?php

namespace App\Http\Requests\Funders;

use App\Models\Funder;
use App\Rules\UniqueFunderName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Nouveau financeur (#249) : un nom et une catégorie (État, collectivité, fondation, autre). */
class StoreFunderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/u', ' ', trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150', new UniqueFunderName($this->ignoreId())],
            'type' => ['sometimes', 'required', Rule::in(Funder::TYPES)],
        ];
    }

    protected function ignoreId(): ?int
    {
        return null;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du financeur.',
            'name.string' => 'Le nom du financeur doit être un texte.',
            'name.max' => 'Le nom du financeur ne doit pas dépasser :max caractères.',
            'type.required' => 'Choisissez la catégorie du financeur.',
            'type.in' => 'Choisissez la catégorie du financeur : État, collectivité, fondation ou autre.',
            'is_active.required' => 'Indiquez si le financeur est actif.',
            'is_active.boolean' => 'Indiquez si le financeur est actif.',
        ];
    }
}
