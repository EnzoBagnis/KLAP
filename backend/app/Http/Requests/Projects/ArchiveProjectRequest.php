<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Archivage d'un projet (#152), administratrice (route). Garde-fou repris de la branche de
 * Lucas (#121) : le nom exact du projet est demandé. Rien n'est effacé (règle de la cliente).
 */
class ArchiveProjectRequest extends FormRequest
{
    private ?Project $project = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('project');
        $this->project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($this->project === null, 404);
        abort_if($this->project->status === 'archived', 409, 'Ce projet est déjà archivé.');

        return true;
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function rules(): array
    {
        return ['confirm_name' => ['required', 'string']];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $typed = self::normalize($this->input('confirm_name'));
                $name = self::normalize($this->project->name);
                if ($typed === $name) {
                    return;
                }
                $validator->errors()->add('confirm_name', mb_strtolower($typed) === mb_strtolower($name)
                    ? "Attention aux majuscules : saisissez exactement « {$this->project->name} »."
                    : 'Le nom saisi ne correspond pas au nom du projet.');
            },
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_name.required' => 'Saisissez le nom du projet pour confirmer son archivage.',
            'confirm_name.string' => 'Saisissez le nom du projet pour confirmer son archivage.',
        ];
    }

    /** Espaces en trop ignorés : seule la saisie du nom compte. */
    private static function normalize(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value));
    }
}
