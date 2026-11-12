<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un projet (écran 58 de la maquette).
 *
 * SQLite ignore les contraintes CHECK de la base : on les reprend donc ici (dates, code
 * postal, séances prévues) pour qu'elles valent partout. On ne lit jamais `created_by` dans
 * la requête : c'est l'utilisateur connecté.
 */
class StoreProjectRequest extends FormRequest
{
    /** Un code postal français : cinq chiffres. */
    private const POSTAL_CODE = 'regex:/^[0-9]{5}$/';

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            // Le type se choisit dans la liste que tient l'administratrice ; on refuse un type désactivé.
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

            // L'intervenant·e principal·e doit être un compte intervenant actif. On ne saisit pas
            // les ponctuel·le·s ici (demande de la cliente, 16/09/2026) : l'administratrice les
            // ajoute ensuite depuis la page du projet (#251).
            'principal_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', 'contributor')->where('is_active', true),
            ],
            // Les financeurs : aucun, un ou plusieurs.
            'funder_ids' => ['nullable', 'array'],
            'funder_ids.*' => ['integer', 'distinct', Rule::exists('funders', 'id')->where('is_active', true)],

            // On saisit ces trois champs au niveau du projet (écran 58), mais c'est chaque
            // sous-projet qui les porte en base : ils servent de valeurs par défaut.
            'organization' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', self::POSTAL_CODE],
            // Projet non découpé (#244) : la tranche d'âge et l'effectif du groupe unique qu'on
            // crée avec lui. On les ignore quand des sous-projets sont donnés : chacun a les siens.
            'age_range' => ['nullable', 'string', 'max:30'],
            'expected_headcount' => ['nullable', 'integer', 'min:1', 'max:65535'],

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

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être un texte.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'array' => 'Le champ :attribute doit être une liste.',
            'date' => 'Le champ :attribute doit être une date valide.',
            // Pour les règles de taille, Laravel attend un tableau par type : il ne lit pas
            // « max.string » écrit à plat.
            'max' => [
                'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
                'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
            ],
            'min' => ['numeric' => 'Le champ :attribute doit valoir au moins :min.'],
            'regex' => 'Le champ :attribute doit contenir 5 chiffres.',
            'sub_projects.*.name.distinct' => 'Deux sous-projets portent le même nom.',
            'funder_ids.*.distinct' => 'Un même financeur est choisi deux fois.',
            'funder_ids.*.exists' => 'Le financeur choisi n’existe pas ou n’est plus actif.',
            'principal_user_id.exists' => 'L’intervenant·e principal·e doit être un compte intervenant actif.',
            'exists' => 'Le :attribute choisi n’existe pas ou n’est plus actif.',
            'in' => 'Le :attribute choisi n’est pas valide à la création.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    /**
     * Les noms des champs, tels qu'on les écrit dans les messages.
     *
     * @return array<string, string>
     */
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
            'principal_user_id' => 'intervenant·e principal·e',
            'funder_ids' => 'financeurs',
            'funder_ids.*' => 'financeur',
            'organization' => 'structure partenaire',
            'city' => 'ville',
            'postal_code' => 'code postal',
            'age_range' => 'tranche d’âge',
            'expected_headcount' => 'effectif prévu',
            'sub_projects' => 'sous-projets',
            'sub_projects.*.name' => 'nom du sous-projet',
            'sub_projects.*.organization' => 'structure du sous-projet',
            'sub_projects.*.class_group' => 'classe ou groupe',
            'sub_projects.*.age_range' => 'tranche d’âge',
            'sub_projects.*.city' => 'ville du sous-projet',
            'sub_projects.*.postal_code' => 'code postal du sous-projet',
            'sub_projects.*.expected_headcount' => 'effectif prévu',
        ];
    }
}
