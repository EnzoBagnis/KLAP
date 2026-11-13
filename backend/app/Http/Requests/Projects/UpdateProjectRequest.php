<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modification partielle d'un projet (#152), administratrice (route). On applique les mêmes
 * règles qu'à la création ; un champ absent garde sa valeur. Les groupes ne se modifient pas
 * ici : ils ont leurs propres routes (#250).
 */
class UpdateProjectRequest extends FormRequest
{
    /** Le projet de l'URL, qu'on charge dans authorize(). */
    private ?Project $project = null;

    /**
     * On vérifie que le projet existe (404) et qu'il n'est pas archivé (409).
     */
    public function authorize(): bool
    {
        $id = (string) $this->route('project');
        $this->project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($this->project === null, 404);
        abort_if($this->project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de le modifier.');

        return true;
    }

    /**
     * Le projet qu'on modifie.
     */
    public function project(): Project
    {
        return $this->project;
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->project;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            // On accepte encore un type ou un lieu devenu inactif, tant qu'il ne change pas.
            'project_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('project_types', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $project->project_type_id))],
            'planned_sessions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            // On n'archive pas ici : l'archivage a sa propre route, avec la confirmation du nom.
            'status' => ['sometimes', 'required', Rule::in(Project::EDITABLE_STATUSES)],
            'default_location_id' => ['sometimes', 'required', 'integer', Rule::exists('locations', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $project->default_location_id))],
            // On accepte encore le ou la principal·e actuel·le, même si son compte a été désactivé (#148).
            'principal_user_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $this->currentLeadIds()))],
            'funder_ids' => ['sometimes', 'nullable', 'array'],
            // On accepte encore un financeur désactivé (#249) sur un projet qui l'a déjà.
            'funder_ids.*' => ['integer', 'distinct', Rule::exists('funders', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $project->funders()->pluck('funders.id')))],
            // Ces champs de l'écran de création ne se modifient pas ici : on les refuse, plutôt
            // que de les ignorer en silence.
            'sub_projects' => ['prohibited'],
            'organization' => ['prohibited'],
            'city' => ['prohibited'],
            'postal_code' => ['prohibited'],
            // L'âge et l'effectif des groupes (#244) se saisissent à la création ou sur le groupe.
            'age_range' => ['prohibited'],
            'expected_headcount' => ['prohibited'],
        ];
    }

    /**
     * Les identifiants des intervenant·e·s principaux·ales actuel·le·s du projet.
     *
     * @return list<int>
     */
    private function currentLeadIds(): array
    {
        return $this->project->members()->wherePivot('member_role', 'lead')->pluck('users.id')->all();
    }

    /**
     * Les contrôles qu'on fait après les règles : ceux qui croisent plusieurs champs ou qui
     * lisent la base.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }
                // On compare avec les valeurs enregistrées : en ne modifiant que la fin, on ne
                // doit pas pouvoir la placer avant le début.
                $start = $this->has('start_date') ? $this->input('start_date') : $this->project->start_date?->toDateString();
                $end = $this->has('end_date') ? $this->input('end_date') : $this->project->end_date?->toDateString();
                if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
                    $validator->errors()->add($this->has('end_date') ? 'end_date' : 'start_date',
                        'La date de fin doit être postérieure ou égale à la date de début.');
                }
            },
        ];
    }

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return (new StoreProjectRequest)->messages() + [
            'status.in' => 'Ce statut n’est pas possible ici : un projet s’archive avec la confirmation de son nom.',
            'prohibited' => 'Les sous-projets, leur structure, leur ville, leur code postal, leur âge et leur effectif ne se modifient pas ici.',
        ];
    }

    /**
     * Les noms des champs, tels qu'on les écrit dans les messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return (new StoreProjectRequest)->attributes();
    }
}
