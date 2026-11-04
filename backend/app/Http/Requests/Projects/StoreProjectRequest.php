<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un projet (écran 58 de la maquette).
 *
 * Les contraintes CHECK de la base sont ignorées sur SQLite : elles sont donc
 * reprises ici (dates, code postal, séances prévues) pour valoir partout.
 * `created_by` n'est jamais lu dans la requête : c'est l'utilisateur connecté.
 */
class StoreProjectRequest extends FormRequest
{
    private const POSTAL_CODE = 'regex:/^[0-9]{5}$/';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            // Type choisi dans la liste gérée par l'administratrice ; un type désactivé n'est plus proposé.
            'project_type_id' => ['nullable', 'integer', Rule::exists('project_types', 'id')->where('is_active', true)],
            'planned_sessions' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(Project::CREATION_STATUSES)],
            'default_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],

            // Saisis au niveau du projet sur l'écran 58, portés en base par chaque
            // sous-projet : ils servent de valeurs par défaut aux sous-projets.
            'organization' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', self::POSTAL_CODE],

            'sub_projects' => ['sometimes', 'array'],
            'sub_projects.*' => ['array'],
            'sub_projects.*.name' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'sub_projects.*.organization' => ['nullable', 'string', 'max:150'],
            'sub_projects.*.class_group' => ['nullable', 'string', 'max:100'],
            'sub_projects.*.age_range' => ['nullable', 'string', 'max:30'],
            'sub_projects.*.city' => ['nullable', 'string', 'max:100'],
            'sub_projects.*.postal_code' => ['nullable', 'string', self::POSTAL_CODE],
            'sub_projects.*.expected_headcount' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être un texte.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'array' => 'Le champ :attribute doit être une liste.',
            'date' => 'Le champ :attribute doit être une date valide.',
            'max.string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'min.numeric' => 'Le champ :attribute doit valoir au moins :min.',
            'max.numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
            'regex' => 'Le champ :attribute doit contenir 5 chiffres.',
            'distinct' => 'Deux sous-projets portent le même nom.',
            'exists' => 'Le :attribute choisi n’existe pas ou n’est plus actif.',
            'in' => 'Le :attribute choisi n’est pas valide à la création.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom du projet',
            'description' => 'description',
            'project_type_id' => 'type de projet',
            'planned_sessions' => 'séances prévues',
            'start_date' => 'début',
            'end_date' => 'fin',
            'status' => 'statut',
            'default_location_id' => 'lieu',
            'organization' => 'structure partenaire',
            'city' => 'ville',
            'postal_code' => 'code postal',
            'sub_projects' => 'sous-projets',
            'sub_projects.*.name' => 'nom du sous-projet',
            'sub_projects.*.organization' => 'structure du sous-projet',
            'sub_projects.*.class_group' => 'classe ou groupe',
            'sub_projects.*.age_range' => 'tranche d’âge',
            'sub_projects.*.city' => 'ville du sous-projet',
            'sub_projects.*.postal_code' => 'code postal du sous-projet',
            'sub_projects.*.expected_headcount' => 'effectif attendu',
        ];
    }
}
