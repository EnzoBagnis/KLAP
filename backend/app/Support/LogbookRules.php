<?php

namespace App\Support;

use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use App\Models\ScheduledSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles de la fiche de séance.
 *
 * Brouillon : tout est facultatif, mais chaque valeur fournie respecte les limites de la
 * base (longueurs, énumérations, notes 1 à 5, 10 exercices au plus), pour qu'aucune
 * contrainte ne remonte en erreur 500. Seule exception : « autre » en sensation exige
 * sa précision dès le brouillon, comme la contrainte chk_eval_feeling_detail.
 *
 * Soumission : la fiche complète, d'après le cahier des charges de la cliente (champs
 * « obligatoire ») et le contexte client (ajustement, évolution, 2 à 10 exercices).
 */
class LogbookRules
{
    public static function draft(ScheduledSession $session): array
    {
        $groupIds = $session->subProjects()->pluck('sub_projects.id')->all();

        return [
            'objectives' => ['nullable', 'string', 'max:400'],
            'adjustment_done' => ['nullable', 'boolean'],
            'adjustment_detail' => ['nullable', 'string', 'max:500'],
            'highlights' => ['nullable', 'string', 'max:200'],
            'tools_resources' => ['nullable', 'string', 'max:200'],
            'learnings' => ['nullable', 'string', 'max:200'],
            'artist_review' => ['nullable', 'string', 'max:200'],
            'next_steps' => ['nullable', 'string', 'max:500'],
            'dropped_leads' => ['nullable', 'string', 'max:500'],
            'keep_in_class' => ['sometimes', 'boolean'],
            'additional_notes' => ['nullable', 'string', 'max:500'],

            'evaluation' => ['sometimes', 'array'],
            'evaluation.engagement' => ['nullable', 'integer', 'between:1,5'],
            'evaluation.expression' => ['nullable', 'integer', 'between:1,5'],
            'evaluation.autonomy' => ['nullable', 'integer', 'between:1,5'],
            'evaluation.interaction' => ['nullable', 'string', 'max:100'],
            'evaluation.feeling' => ['nullable', Rule::in(ParticipantEvaluation::FEELINGS)],
            // « autre » exige sa précision dès le brouillon : vérifié sur l'état complet (draftErrors).
            'evaluation.feeling_detail' => ['nullable', 'string', 'max:100'],
            'evaluation.progress' => ['nullable', Rule::in(ParticipantEvaluation::PROGRESS)],

            'exercises' => ['sometimes', 'array', 'max:10'],
            'exercises.*' => ['required', 'string', 'max:200'],

            // Lieu de la séance, corrigé depuis la fiche (#215) : « le lieu est modifiable par tout
            // utilisateur » (projets en tournée). Un lieu actif, ou le lieu actuel même désactivé
            // depuis (comme au planning) : la fiche renvoie son lieu à chaque enregistrement.
            'location_id' => ['sometimes', 'required', 'integer', Rule::exists('locations', 'id')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $session->location_id))],

            // Présents par groupe : seulement les groupes de la séance (le planning les fixe).
            'attendees' => ['sometimes', 'array'],
            'attendees.*.sub_project_id' => ['required', 'integer', 'distinct', Rule::in($groupIds)],
            'attendees.*.count' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * Seule règle de soumission qui vaut déjà au brouillon : « autre » sans précision est
     * refusé par la base (chk_eval_feeling_detail), quel que soit le statut. Vérifiée sur
     * l'état complet (enregistré + saisie), pas sur la seule saisie.
     */
    public static function draftErrors(array $state): array
    {
        $evaluation = $state['evaluation'] ?? [];
        $detail = trim((string) ($evaluation['feeling_detail'] ?? ''));

        return ($evaluation['feeling'] ?? null) === 'other' && $detail === ''
            ? ['evaluation.feeling_detail' => 'Précisez la sensation quand vous choisissez « autre ».']
            : [];
    }

    /**
     * « Rien » est une réponse acceptée aux temps forts quand il n'y en a pas eu (#225), quelle
     * que soit la façon de l'écrire : « rien », « Rien. », « RAS », « néant ». Le pré-bilan écarte
     * ensuite ces réponses (PreReportService::EMPTY_ANSWERS).
     */
    private static function saysNothing(string $text): bool
    {
        return in_array(self::plain($text), ['rien', 'ras', 'r.a.s', 'neant'], true);
    }

    /**
     * Forme comparable d'une réponse courte : sans accent, en minuscules, sans ponctuation aux deux
     * bouts. Partagée avec le pré-bilan, pour que la fiche et le document jugent pareil (#229).
     */
    public static function plain(string $text): string
    {
        return mb_strtolower(trim(Str::ascii($text), ' .!'));
    }

    public static function messages(): array
    {
        return [
            'string' => 'Le champ :attribute doit être un texte.',
            'max.string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'max.array' => 'Une fiche compte au plus :max exercices.',
            'boolean' => 'Le champ :attribute doit valoir oui ou non.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'between.numeric' => 'La note :attribute va de :min à :max.',
            'min.numeric' => 'Le champ :attribute doit valoir au moins :min.',
            'in' => 'La valeur de :attribute n’est pas valide.',
            'exercises.*.required' => 'Un exercice ne peut pas être vide.',
            'location_id.required' => 'Choisissez le lieu de la séance.',
            'location_id.exists' => 'Le lieu choisi n’existe pas ou n’est plus actif.',
            'evaluation.feeling_detail.required_if' => 'Précisez la sensation quand vous choisissez « autre ».',
            'attendees.*.sub_project_id.in' => 'Ce groupe ne fait pas partie de la séance.',
            'attendees.*.sub_project_id.distinct' => 'Un même groupe est indiqué deux fois.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'objectives' => 'objectifs', 'adjustment_done' => 'ajustement', 'adjustment_detail' => 'explication de l’ajustement',
            'highlights' => 'temps forts', 'tools_resources' => 'outils et ressources', 'learnings' => 'acquis',
            'artist_review' => 'bilan de l’intervenant·e', 'next_steps' => 'à reprendre', 'dropped_leads' => 'pistes abandonnées',
            'keep_in_class' => 'maintien en classe', 'additional_notes' => 'notes complémentaires',
            'evaluation.engagement' => 'd’engagement', 'evaluation.expression' => 'd’expression', 'evaluation.autonomy' => 'd’autonomie',
            'evaluation.interaction' => 'interaction', 'evaluation.feeling' => 'sensation', 'evaluation.feeling_detail' => 'précision de la sensation',
            'evaluation.progress' => 'évolution', 'exercises' => 'exercices', 'exercises.*' => 'exercice',
            'attendees.*.count' => 'présents', 'location_id' => 'lieu',
        ];
    }

    /**
     * État complet de la fiche après application d'une saisie (sans rien écrire) :
     * sert à valider une soumission, ou une modification d'une fiche déjà soumise.
     */
    public static function state(LogbookEntry $entry, array $input = []): array
    {
        // « Non fait » : aucune explication n'est demandée, l'ancienne est effacée (voir apply()).
        if (array_key_exists('adjustment_done', $input) && $input['adjustment_done'] === false) {
            $input['adjustment_detail'] = null;
        }
        $fields = array_merge($entry->only(LogbookEntry::FIELDS), array_intersect_key($input, array_flip(LogbookEntry::FIELDS)));
        $evaluation = array_merge(
            $entry->evaluation?->only(ParticipantEvaluation::FIELDS) ?? [],
            $input['evaluation'] ?? [],
        );
        $exercises = $input['exercises'] ?? $entry->exercises->pluck('label')->all();
        $attendees = $entry->scheduled_session_id === null ? [] : DB::table('scheduled_session_sub_project')
            ->where('scheduled_session_id', $entry->scheduled_session_id)
            ->pluck('attendees', 'sub_project_id')
            ->all();
        foreach ($input['attendees'] ?? [] as $row) {
            $attendees[$row['sub_project_id']] = $row['count'] ?? null;
        }

        return $fields + ['evaluation' => $evaluation, 'exercises' => $exercises, 'attendees' => $attendees];
    }

    /** Erreurs qui empêchent la soumission, par clé de champ. Vide si la fiche est complète. */
    public static function submissionErrors(array $state, ScheduledSession $session): array
    {
        $errors = [];
        $filled = fn ($value) => is_string($value) ? trim($value) !== '' : $value !== null;

        // La séance doit être terminée (date et heure de fin, à l'heure de KLAP).
        $end = Carbon::parse(
            $session->session_date->toDateString().' '.substr((string) $session->end_time, 0, 5),
            LogbookEntry::LOCAL_TIMEZONE,
        );
        if (LogbookEntry::localNow()->lt($end)) {
            $errors['session'] = 'La séance n’est pas encore terminée : la fiche se soumet après.';
        }
        if (! $filled($state['objectives'] ?? null)) {
            $errors['objectives'] = 'Indiquez les objectifs pédagogiques et artistiques.';
        }
        $highlights = trim((string) ($state['highlights'] ?? ''));
        if (mb_strlen($highlights) < 5 && ! self::saysNothing($highlights)) {
            $errors['highlights'] = 'Décrivez les temps forts (5 caractères au moins, « rien » est accepté s’il n’y en a pas eu).';
        }
        $count = count($state['exercises'] ?? []);
        if ($count < 2 || $count > 10) {
            $errors['exercises'] = 'Une fiche compte de 2 à 10 exercices.';
        }
        if (($state['adjustment_done'] ?? null) === null) {
            $errors['adjustment_done'] = 'Indiquez si un ajustement a été fait.';
        } elseif ($state['adjustment_done']) {
            $length = mb_strlen(trim((string) ($state['adjustment_detail'] ?? '')));
            if ($length < 5 || $length > 500) {
                $errors['adjustment_detail'] = 'Expliquez quel ajustement et pourquoi (5 à 500 caractères).';
            }
        }

        $evaluation = $state['evaluation'] ?? [];
        foreach (['engagement' => 'l’engagement', 'expression' => 'l’expression', 'autonomy' => 'l’autonomie'] as $key => $label) {
            if (! $filled($evaluation[$key] ?? null)) {
                $errors["evaluation.$key"] = "Notez $label de 1 à 5.";
            }
        }
        if (! $filled($evaluation['interaction'] ?? null)) {
            $errors['evaluation.interaction'] = 'Décrivez l’interaction entre les participants.';
        }
        if (! $filled($evaluation['feeling'] ?? null)) {
            $errors['evaluation.feeling'] = 'Choisissez la sensation dominante.';
        } elseif ($evaluation['feeling'] === 'other' && ! $filled($evaluation['feeling_detail'] ?? null)) {
            $errors['evaluation.feeling_detail'] = 'Précisez la sensation quand vous choisissez « autre ».';
        }
        if (! $filled($evaluation['progress'] ?? null)) {
            $errors['evaluation.progress'] = 'Indiquez l’évolution par rapport à la séance précédente.';
        }

        if (! $filled($state['next_steps'] ?? null)) {
            $errors['next_steps'] = 'Indiquez ce qui est à reprendre.';
        }

        // Un nombre de présents (au moins 1) pour chaque groupe : une séance sans personne est annulée.
        foreach ($state['attendees'] as $count) {
            if ($count === null || (int) $count < 1) {
                $errors['attendees'] = 'Indiquez le nombre de présents de chaque groupe (1 au moins).';
                break;
            }
        }

        return $errors;
    }
}
