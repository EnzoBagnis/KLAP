<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'un projet de danse KLAP.
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'default_location_id' => ['required', 'integer', 'exists:locations,id'],
            'lead_artist_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:draft,active,finished,archived'],
            'funder_ids' => ['nullable', 'array'],
            'funder_ids.*' => ['integer', 'exists:funders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du projet est obligatoire.',
            'name.max' => 'Le nom ne peut dépasser 150 caractères.',
            'default_location_id.required' => 'Le lieu principal par défaut est obligatoire.',
            'default_location_id.exists' => 'Le lieu sélectionné est introuvable.',
            'lead_artist_id.required' => "L'intervenant principal est obligatoire.",
            'lead_artist_id.exists' => "L'artiste sélectionné est introuvable.",
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
