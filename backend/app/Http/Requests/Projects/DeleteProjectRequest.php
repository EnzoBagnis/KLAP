<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation de la suppression sécurisée d'un projet de danse.
 * Règle demandée par Mariette Travard :
 * L'utilisateur doit impérativement saisir le nom exact du projet pour confirmer la suppression irréversible.
 */
class DeleteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $this->user()->can('delete', $project);
    }

    /**
     * Nettoie les espaces multiples internes avant validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('confirm_name')) {
            $this->merge([
                'confirm_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('confirm_name'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_name.required' => 'Vous devez saisir le nom exact du projet pour confirmer sa suppression définitive.',
        ];
    }

    /**
     * Valide que le nom saisi correspond exactement au nom du projet ciblé.
     * Fournit un guidage UX précis si c'est simplement un problème de majuscules/minuscules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');

            if ($project instanceof Project) {
                $inputName = preg_replace('/\s+/', ' ', trim((string) $this->input('confirm_name')));
                $projectName = preg_replace('/\s+/', ' ', trim((string) $project->name));

                if ($inputName !== $projectName) {
                    if (mb_strtolower($inputName) === mb_strtolower($projectName)) {
                        $validator->errors()->add(
                            'confirm_name',
                            "Attention à la casse (majuscules/minuscules) : veuillez saisir exactement « {$project->name} »."
                        );
                    } else {
                        $validator->errors()->add(
                            'confirm_name',
                            "Le nom saisi ne correspond pas exactement au nom du projet (« {$project->name} »)."
                        );
                    }
                }
            }
        });
    }
}
