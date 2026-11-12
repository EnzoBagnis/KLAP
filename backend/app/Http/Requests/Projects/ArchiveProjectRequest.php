<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Archivage d'un projet (#152), administratrice (route). On a repris le garde-fou de la
 * branche de Lucas (#121) : on demande le nom exact du projet. On n'efface rien (règle de la
 * cliente).
 */
class ArchiveProjectRequest extends FormRequest
{
    /** Le projet de l'URL, qu'on charge dans authorize(). */
    private ?Project $project = null;

    /**
     * On vérifie que le projet existe (404) et qu'il n'est pas déjà archivé (409).
     */
    public function authorize(): bool
    {
        $id = (string) $this->route('project');
        $this->project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($this->project === null, 404);
        abort_if($this->project->status === 'archived', 409, 'Ce projet est déjà archivé.');

        return true;
    }

    /**
     * Le projet qu'on archive.
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
        return ['confirm_name' => ['required', 'string']];
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

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'confirm_name.required' => 'Saisissez le nom du projet pour confirmer son archivage.',
            'confirm_name.string' => 'Saisissez le nom du projet pour confirmer son archivage.',
        ];
    }

    /**
     * On ignore les espaces en trop : seule la saisie du nom compte.
     */
    private static function normalize(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value));
    }
}
