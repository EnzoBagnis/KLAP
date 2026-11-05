<?php

namespace App\Http\Requests\Logbook;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation finale et complète de la fiche de carnet de bord (6 étapes).
 * Conforme à 100% au modèle KLAP Kelemenis & cie et aux retours de Mariette Travard :
 * - Étape 1 : Effectif total obligatoire, F/G et âge/classe.
 * - Étape 2 : Objectifs pédagogiques et artistiques.
 * - Étape 3 : Déroulé et exercices (1 à 10), et justification obligatoire si ajustement effectué.
 * - Étape 4 : Évaluations des participants (échelles 1 à 5, ressenti, progression).
 * - Étape 5 : Bilan artiste (« Ce qui a fonctionné » obligatoire, notes complémentaires max 1000 car).
 * - Étape 6 : Perspectives (« À reprendre » obligatoire, maintien en classe, souhaits).
 */
class SubmitLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->is_active;
    }

    /**
     * Nettoie les espaces multiples internes avant validation.
     */
    protected function prepareForValidation(): void
    {
        $fields = ['what_works', 'next_session_notes', 'learnings', 'to_watch', 'to_improve', 'additional_notes'];
        $merges = [];
        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $merges[$field] = preg_replace('/\s+/', ' ', trim($this->input($field)));
            }
        }
        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        return [
            'scheduled_session_id' => ['required', 'integer', 'exists:scheduled_sessions,id'],

            // Étape 1 : En-tête & Généralités
            'attendees_count' => ['required', 'integer', 'min:0'],
            'attendees_male' => ['nullable', 'integer', 'min:0'],
            'attendees_female' => ['nullable', 'integer', 'min:0'],
            'class_or_age' => ['nullable', 'string', 'max:80'],

            // Étape 2 : Objectifs
            'pedagogical_goals' => ['nullable', 'string', 'max:1000'],
            'artistic_goals' => ['nullable', 'string', 'max:1000'],
            'competencies' => ['nullable', 'string', 'max:1000'],
            'progression' => ['nullable', 'string', 'max:1000'],

            // Étape 3 : Déroulé de séance
            'warmup_desc' => ['nullable', 'string', 'max:1000'],
            'closing' => ['nullable', 'string', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:1000'],
            'adjustments_made' => ['required', 'boolean'],
            'adjustments_reason' => [
                'nullable',
                'string',
                'max:500',
                'required_if:adjustments_made,true',
                'min:5',
            ],
            'exercises' => ['nullable', 'array', 'max:10'],
            'exercises.*.id' => ['nullable', 'integer'],
            'exercises.*.order' => ['required', 'integer', 'between:1,10'],
            'exercises.*.name' => ['required', 'string', 'max:150'],
            'exercises.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'exercises.*.description' => ['nullable', 'string', 'max:1000'],

            // Étape 4 : Observations sur les participants
            'evaluations' => ['nullable', 'array'],
            'evaluations.*.engagement_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.expression_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.autonomy_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.interactions_comment' => ['nullable', 'string', 'max:255'],
            'evaluations.*.feelings_choice' => ['nullable', 'string', 'max:100'],
            'evaluations.*.feelings_custom' => ['nullable', 'string', 'max:150'],
            'evaluations.*.progression_trend' => ['nullable', 'string', 'in:recul,constant,progres,tres_fort_progres,first_session'],

            // Étape 5 : Les artistes / Les pros
            'what_works' => ['required', 'string', 'min:5', 'max:500'],
            'learnings' => ['nullable', 'string', 'max:1000'],
            'to_watch' => ['nullable', 'string', 'max:1000'],
            'to_improve' => ['nullable', 'string', 'max:1000'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],

            // Étape 6 : Perspectives / La suite
            'next_session_notes' => ['required', 'string', 'max:500'],
            'maintain_in_class' => ['nullable', 'boolean'],
            'discarded_paths' => ['nullable', 'string', 'max:1000'],
            'participant_wishes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendees_count.required' => 'Le nombre total de participants est obligatoire.',
            'adjustments_made.required' => 'Veuillez indiquer si des ajustements ont été réalisés.',
            'adjustments_reason.required_if' => 'La justification des ajustements est obligatoire lorsque l’option « Fait » est cochée.',
            'adjustments_reason.min' => 'La justification des ajustements doit comporter au moins 5 caractères.',
            'what_works.required' => 'Le champ « Ce qui a fonctionné » est obligatoire.',
            'what_works.min' => '« Ce qui a fonctionné » doit comporter au moins 5 caractères.',
            'next_session_notes.required' => 'Le champ « Perspectives / À reprendre » est obligatoire.',
            'exercises.*.name.required' => 'Le nom de l’exercice est obligatoire.',
            'additional_notes.max' => 'Les notes complémentaires ne peuvent excéder 1000 caractères.',
        ];
    }

    /**
     * Valide la cohérence entre l'effectif total et la ventilation filles/garçons.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $total = (int) $this->input('attendees_count', 0);
            $male = $this->filled('attendees_male') ? (int) $this->input('attendees_male') : null;
            $female = $this->filled('attendees_female') ? (int) $this->input('attendees_female') : null;

            if ($male !== null && $female !== null) {
                if (($male + $female) > $total) {
                    $validator->errors()->add(
                        'attendees_count',
                        "La somme des garçons ({$male}) et des filles ({$female}) ne peut pas dépasser l'effectif total ({$total})."
                    );
                }
            }
        });
    }
}
