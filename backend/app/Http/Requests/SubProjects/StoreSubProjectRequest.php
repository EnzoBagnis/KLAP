<?php

namespace App\Http\Requests\SubProjects;

use App\Models\Project;
use App\Models\SubProject;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajouter un groupe (sous-projet) à un projet existant (#250), administratrice seulement.
 * Les champs sont ceux de la création d'un projet ; le nom est unique dans le projet, sans tenir
 * compte de la casse.
 */
class StoreSubProjectRequest extends FormRequest
{
    protected const POSTAL_CODE = 'regex:/^[0-9]{5}$/';

    public function project(): Project
    {
        $id = (string) $this->route('project');
        $project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($project === null, 404);

        return $project;
    }

    protected function prepareForValidation(): void
    {
        $tidy = fn ($value) => is_string($value) ? (($v = preg_replace('/\s+/u', ' ', trim($value))) === '' ? null : $v) : $value;
        $this->merge(collect(['name', 'organization', 'class_group', 'age_range', 'city', 'postal_code'])
            ->filter(fn ($key) => $this->has($key))
            ->mapWithKeys(fn ($key) => [$key => $tidy($this->input($key))])
            ->all());
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150', $this->uniqueName()],
            'organization' => ['nullable', 'string', 'max:150'],
            'class_group' => ['nullable', 'string', 'max:100'],
            'age_range' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', self::POSTAL_CODE],
            'expected_headcount' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /** Nom unique dans le projet, sans tenir compte de la casse. */
    protected function uniqueName(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
            $taken = SubProject::query()
                ->where('project_id', $this->project()->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();
            if ($taken) {
                $fail('Ce projet a déjà un groupe de ce nom.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du groupe.',
            'string' => 'Le champ :attribute doit être un texte.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'max' => ['string' => 'Le champ :attribute ne doit pas dépasser :max caractères.', 'numeric' => 'Le champ :attribute ne doit pas dépasser :max.'],
            'min' => ['numeric' => 'Le champ :attribute doit valoir au moins :min.'],
            'postal_code.regex' => 'Le code postal doit compter 5 chiffres.',
            'is_active.boolean' => 'Indiquez si le groupe est actif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom du groupe', 'organization' => 'structure', 'class_group' => 'classe ou groupe',
            'age_range' => 'tranche d’âge', 'city' => 'ville', 'postal_code' => 'code postal', 'expected_headcount' => 'effectif prévu',
        ];
    }
}
