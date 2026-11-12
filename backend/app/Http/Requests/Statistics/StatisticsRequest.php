<?php

namespace App\Http\Requests\Statistics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Filtres des statistiques : un projet et une période (date de séance, bornes incluses).
 * On les laisse tous facultatifs.
 */
class StatisticsRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['bail', 'nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            // On le compare à `from` dans after() : la règle after_or_equal plante si `from` est un tableau.
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
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
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }
                if (Carbon::parse($this->input('to'))->lt(Carbon::parse($this->input('from')))) {
                    $validator->errors()->add('to', 'La fin de la période doit être après son début.');
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
        return [
            'project_id.integer' => 'Le projet demandé n’existe pas.',
            'project_id.exists' => 'Le projet demandé n’existe pas.',
            'date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ];
    }
}
