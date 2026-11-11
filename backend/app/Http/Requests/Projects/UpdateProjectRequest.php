<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modification partielle d'un projet (#152), administratrice (route). Mêmes règles qu'à la
 * création ; un champ absent garde sa valeur. Les sous-projets ne se modifient pas ici.
 */
class UpdateProjectRequest extends FormRequest
{
    private ?Project $project = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('project');
        $this->project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($this->project === null, 404);
        abort_if($this->project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de le modifier.');

        return true;
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function rules(): array
    {
        $project = $this->project;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            // Un type ou un lieu devenu inactif reste accepté tant qu'il ne change pas.
            'project_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('project_types', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $project->project_type_id))],
            'planned_sessions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            // L'archivage passe par sa propre route, avec la confirmation du nom.
            'status' => ['sometimes', 'required', Rule::in(Project::EDITABLE_STATUSES)],
            'default_location_id' => ['sometimes', 'required', 'integer', Rule::exists('locations', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $project->default_location_id))],
            // Le ou la principal·e actuel·le reste accepté·e même si son compte a été désactivé (#148).
            'principal_user_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where('role', 'contributor')
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $this->currentLeadIds()))],
            'funder_ids' => ['sometimes', 'nullable', 'array'],
            // Un financeur désactivé (#249) reste accepté sur un projet qui l'a déjà.
            'funder_ids.*' => ['integer', 'distinct', Rule::exists('funders', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $project->funders()->pluck('funders.id')))],
            // Champs de l'écran de création qui ne se modifient pas ici : refusés plutôt qu'ignorés.
            'sub_projects' => ['prohibited'],
            'organization' => ['prohibited'],
            'city' => ['prohibited'],
            'postal_code' => ['prohibited'],
            // Âge et effectif des groupes (#244) : saisis à la création, pas modifiés ici.
            'age_range' => ['prohibited'],
            'expected_headcount' => ['prohibited'],
        ];
    }

    private function currentLeadIds(): array
    {
        return $this->project->members()->wherePivot('member_role', 'lead')->pluck('users.id')->all();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }
                // Comparées aux valeurs enregistrées : modifier seulement la fin ne la place pas avant le début.
                $start = $this->has('start_date') ? $this->input('start_date') : $this->project->start_date?->toDateString();
                $end = $this->has('end_date') ? $this->input('end_date') : $this->project->end_date?->toDateString();
                if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
                    $validator->errors()->add($this->has('end_date') ? 'end_date' : 'start_date',
                        'La date de fin doit être postérieure ou égale à la date de début.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return (new StoreProjectRequest)->messages() + [
            'status.in' => 'Ce statut n’est pas possible ici : un projet s’archive avec la confirmation de son nom.',
            'prohibited' => 'Les sous-projets, leur structure, leur ville, leur code postal, leur âge et leur effectif ne se modifient pas ici.',
        ];
    }

    public function attributes(): array
    {
        return (new StoreProjectRequest)->attributes();
    }
}
