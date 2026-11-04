<?php

namespace App\Http\Requests\Projects;

use App\Rules\UniqueProjectTypeName;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new UniqueProjectTypeName],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du type de projet est obligatoire.',
            'name.string' => 'Le nom du type de projet doit être un texte.',
            'name.max' => 'Le nom du type de projet ne doit pas dépasser :max caractères.',
        ];
    }
}
