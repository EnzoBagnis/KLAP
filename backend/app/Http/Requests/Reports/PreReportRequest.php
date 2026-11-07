<?php

namespace App\Http\Requests\Reports;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Pré-bilan Word (#143) : un projet, un financeur de ce projet, une période facultative
 * (date de séance, bornes incluses). Administratrice et super-utilisatrice (route).
 */
class PreReportRequest extends FormRequest
{
    private ?Project $project = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('project');
        $this->project = ctype_digit($id) ? Project::query()->find($id) : null;
        abort_if($this->project === null, 404);

        return true;
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function rules(): array
    {
        return [
            'funder_id' => ['bail', 'required', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            // Comparé à `from` dans after() : la règle after_or_equal plante si `from` est un tableau.
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $errors = $validator->errors();
                if (! $errors->has('funder_id') && ! $this->project->funders()->whereKey($this->input('funder_id'))->exists()) {
                    $errors->add('funder_id', 'Ce financeur ne finance pas ce projet.');
                }
                if (! $errors->has('from') && ! $errors->has('to') && $this->filled('from') && $this->filled('to')
                    && Carbon::parse($this->input('to'))->lt(Carbon::parse($this->input('from')))) {
                    $validator->errors()->add('to', 'La fin de la période doit être après son début.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'funder_id.required' => 'Choisissez le financeur.',
            'funder_id.integer' => 'Ce financeur ne finance pas ce projet.',
            'date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ];
    }
}
