<?php

namespace App\Http\Requests\Sessions;

use App\Models\Project;
use App\Models\ScheduledSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Programmer une séance (maquette, écran 52). Les contraintes CHECK de la base
 * (horaires, précision du type « autre ») et la clé étrangère composite du pivot
 * (sous-projets du même projet) sont reprises ici pour répondre en 422 partout,
 * SQLite compris. `status` et `created_by` ne sont jamais lus dans la requête.
 */
class StoreScheduledSessionRequest extends FormRequest
{
    private ?Project $project = null;

    /** Projet inexistant : 404, avant toute validation. */
    public function authorize(): bool
    {
        // Identifiant non numérique (« 1abc ») : 404, sans conversion implicite par la base.
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
        $projectId = $this->project->id;

        return [
            'session_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            // Comparé au début dans after() : la règle after plante si le début est un tableau.
            'end_time' => ['required', 'date_format:H:i'],
            'session_type' => ['required', Rule::in(ScheduledSession::TYPES)],
            'session_type_detail' => ['nullable', 'required_if:session_type,other', 'prohibited_unless:session_type,other', 'string', 'max:100'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'referent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::exists('project_user', 'user_id')->where('project_id', $projectId),
            ],
            // Au moins un groupe : toutes les statistiques passent par ce lien.
            'sub_project_ids' => ['required', 'array', 'min:1'],
            'sub_project_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('sub_projects', 'id')->where('project_id', $projectId)->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->project->status === 'archived') {
                    $validator->errors()->add('project', 'Ce projet est archivé : on ne peut plus y programmer de séance.');
                }

                $errors = $validator->errors();
                if (! $errors->hasAny(['start_time', 'end_time']) && $this->input('end_time') <= $this->input('start_time')) {
                    $errors->add('end_time', 'La fin de la séance doit être après son début.');
                }

                // Le référent doit être affecté au projet à la date de la séance.
                if (! $errors->hasAny(['referent_id', 'session_date'])) {
                    $assignment = DB::table('project_user')
                        ->where('project_id', $this->project->id)
                        ->where('user_id', $this->input('referent_id'))
                        ->first(['start_date', 'end_date']);
                    $date = $this->input('session_date');
                    if ($assignment && (($assignment->start_date && $date < $assignment->start_date)
                        || ($assignment->end_date && $date > $assignment->end_date))) {
                        $errors->add('referent_id', 'L’intervenant·e n’est pas affecté·e au projet à cette date.');
                    }
                }

                // Un·e intervenant·e n'anime pas deux séances à la fois (#168) : doublon ou erreur de saisie.
                if (! $errors->hasAny(['referent_id', 'session_date', 'start_time', 'end_time'])) {
                    $clash = $this->overlappingSession();
                    if ($clash !== null) {
                        $errors->add('start_time', sprintf('Cet·te intervenant·e a déjà une séance de %s à %s ce jour-là (%s).',
                            substr((string) $clash->start_time, 0, 5), substr((string) $clash->end_time, 0, 5), $clash->project_name));
                    }
                }
            },
        ];
    }

    /**
     * Séance non annulée du même référent, le même jour, dont l'horaire chevauche celui demandé.
     * Comparaison en PHP sur « HH:MM » : SQLite et MariaDB ne stockent pas l'heure au même format,
     * et deux séances accolées (10 h - 12 h, puis 12 h - 14 h) ne se chevauchent pas.
     */
    private function overlappingSession(): ?object
    {
        $start = substr((string) $this->input('start_time'), 0, 5);
        $end = substr((string) $this->input('end_time'), 0, 5);

        return DB::table('scheduled_sessions as s')
            ->join('projects as p', 'p.id', '=', 's.project_id')
            ->where('s.referent_id', $this->input('referent_id'))
            ->whereDate('s.session_date', $this->input('session_date'))
            ->where('s.status', '<>', 'cancelled')
            ->get(['s.start_time', 's.end_time', 'p.name as project_name'])
            ->first(fn ($other) => substr((string) $other->start_time, 0, 5) < $end && $start < substr((string) $other->end_time, 0, 5));
    }

    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'session_type_detail.required_if' => 'Précisez le type de séance quand vous choisissez « autre ».',
            'date_format' => 'Le champ :attribute n’a pas le bon format.',
            'session_type_detail.prohibited_unless' => 'La précision ne s’emploie qu’avec le type « autre ».',
            'in' => 'Le :attribute choisi n’est pas valide.',
            'max' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'location_id.exists' => 'Le lieu choisi n’existe pas ou n’est plus actif.',
            'referent_id.exists' => 'L’intervenant·e doit être un compte actif affecté au projet.',
            'sub_project_ids.min' => 'Choisissez au moins un groupe.',
            'sub_project_ids.*.exists' => 'Ce groupe n’appartient pas au projet ou n’est plus actif.',
            'sub_project_ids.*.distinct' => 'Un même groupe est choisi deux fois.',
        ];
    }

    public function attributes(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'début',
            'end_time' => 'fin',
            'session_type' => 'type de séance',
            'session_type_detail' => 'précision du type',
            'location_id' => 'lieu',
            'referent_id' => 'intervenant·e',
            'sub_project_ids' => 'groupes',
        ];
    }
}
