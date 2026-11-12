<?php

namespace App\Http\Requests\SubProjects;

use App\Models\Project;
use App\Models\SubProject;

/**
 * Corriger, retirer ou remettre un groupe (#250). Retirer, c'est désactiver : on n'efface rien.
 */
class UpdateSubProjectRequest extends StoreSubProjectRequest
{
    /** Le groupe de l'URL, qu'on charge au premier appel de group(). */
    private ?SubProject $group = null;

    /**
     * Le groupe qu'on modifie ; 404 s'il n'existe pas.
     */
    public function group(): SubProject
    {
        if ($this->group === null) {
            $id = (string) $this->route('subProject');
            $this->group = ctype_digit($id) ? SubProject::query()->find($id) : null;
            abort_if($this->group === null, 404);
        }

        return $this->group;
    }

    /**
     * Le projet du groupe.
     */
    public function project(): Project
    {
        return $this->group()->project;
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())->map(fn ($r) => ['sometimes', ...$r])->all();
        $rules['name'] = ['bail', 'sometimes', 'required', 'string', 'max:150', $this->uniqueName($this->group()->id)];
        $rules['is_active'] = ['sometimes', 'required', 'boolean'];

        return $rules;
    }
}
