<?php

namespace App\Http\Requests\Logbook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour l'enregistrement partiel / brouillon automatique (Auto-save) d'une fiche.
 * Permet aux intervenants de sauvegarder en cours de séance sur mobile
 * sans être bloqués si toutes les étapes ne sont pas encore complètes.
 */
class DraftLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'scheduled_session_id' => ['required', 'integer', 'exists:scheduled_sessions,id'],

            // Étape 1 : Généralités & Effectifs
            'attendees_count' => ['nullable', 'integer', 'min:0'],
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
            'adjustments_made' => ['nullable', 'boolean'],
            'adjustments_reason' => ['nullable', 'string', 'max:500'],
            'exercises' => ['nullable', 'array'],
            'exercises.*.id' => ['nullable', 'integer'],
            'exercises.*.order' => ['nullable', 'integer', 'between:1,10'],
            'exercises.*.name' => ['nullable', 'string', 'max:150'],
            'exercises.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'exercises.*.description' => ['nullable', 'string', 'max:1000'],

            // Étape 4 : Observations
            'evaluations' => ['nullable', 'array'],
            'evaluations.*.engagement_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.expression_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.autonomy_score' => ['nullable', 'integer', 'between:1,5'],
            'evaluations.*.interactions_comment' => ['nullable', 'string', 'max:255'],
            'evaluations.*.feelings_choice' => ['nullable', 'string', 'max:100'],
            'evaluations.*.feelings_custom' => ['nullable', 'string', 'max:150'],
            'evaluations.*.progression_trend' => ['nullable', 'string', 'in:recul,constant,progres,tres_fort_progres'],

            // Étape 5 : Les artistes / Les pros
            'what_works' => ['nullable', 'string', 'max:500'],
            'learnings' => ['nullable', 'string', 'max:1000'],
            'to_watch' => ['nullable', 'string', 'max:1000'],
            'to_improve' => ['nullable', 'string', 'max:1000'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],

            // Étape 6 : Perspectives / La suite
            'next_session_notes' => ['nullable', 'string', 'max:1000'],
            'maintain_in_class' => ['nullable', 'boolean'],
            'discarded_paths' => ['nullable', 'string', 'max:1000'],
            'participant_wishes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
