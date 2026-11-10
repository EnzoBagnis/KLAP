<?php

namespace App\Http\Requests\SubProjects;

use App\Models\Project;
use App\Models\SubProject;

/** Corriger, retirer ou remettre un groupe (#250). Retirer désactive : rien n'est effacé. */
class UpdateSubProjectRequest extends StoreSubProjectRequest
{
    private ?SubProject $group = null;

    public function group(): SubProject
    {
        if ($this->group === null) {
            $id = (string) $this->route('subProject');
            $this->group = ctype_digit($id) ? SubProject::query()->find($id) : null;
            abort_if($this->group === null, 404);
        }

        return $this->group;
    }

    public function project(): Project
    {
        return $this->group()->project;
    }

    public function rules(): array
    {
        $rules = collect(parent::rules())->map(fn ($r) => ['sometimes', ...$r])->all();
        $rules['name'] = ['bail', 'sometimes', 'required', 'string', 'max:150', $this->uniqueName($this->group()->id)];
        $rules['is_active'] = ['sometimes', 'required', 'boolean'];

        return $rules;
    }
}
