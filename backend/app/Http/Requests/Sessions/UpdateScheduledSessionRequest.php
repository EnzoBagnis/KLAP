<?php

namespace App\Http\Requests\Sessions;

use App\Models\LogbookEntry;
use App\Models\ScheduledSession;
use Illuminate\Validation\Rule;

/**
 * Modifier une séance (#180), administratrice (route) : on reprend les champs et les règles
 * de la programmation, sans compter la séance elle-même dans les chevauchements. On refuse
 * dès que la séance a une fiche (son échéance et ses groupes en dépendent), si elle est
 * annulée, ou si son délai de J+10 est passé (la déplacer rouvrirait une fiche close).
 */
class UpdateScheduledSessionRequest extends StoreScheduledSessionRequest
{
    /** La séance de l'URL, qu'on charge dans authorize(). */
    private ?ScheduledSession $scheduled = null;

    /**
     * On vérifie que la séance existe (404) et qu'elle peut encore être modifiée (409).
     */
    public function authorize(): bool
    {
        $id = (string) $this->route('session');
        $this->scheduled = ctype_digit($id) ? ScheduledSession::query()->with('project')->find($id) : null;
        abort_if($this->scheduled === null, 404);
        abort_if($this->scheduled->status === 'cancelled', 409, 'Cette séance est annulée : rétablissez-la avant de la modifier.');
        abort_if($this->scheduled->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche : elle ne se modifie plus.');
        $deadline = LogbookEntry::deadlineFor($this->scheduled->session_date)->toDateString();
        abort_if(LogbookEntry::localNow()->toDateString() > $deadline, 409,
            'Le délai de 10 jours après cette séance est passé : elle ne se modifie plus.');
        $this->project = $this->scheduled->project;

        return true;
    }

    /**
     * La séance qu'on modifie.
     */
    public function scheduledSession(): ScheduledSession
    {
        return $this->scheduled;
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        // On accepte encore le lieu actuel, même s'il a été désactivé depuis.
        $rules['location_id'] = ['required', 'integer', Rule::exists('locations', 'id')
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->scheduled->location_id))];

        // On accepte encore les groupes déjà liés à la séance, même s'ils ont été retirés depuis.
        $linked = $this->scheduled->subProjects()->pluck('sub_projects.id')->all();
        $rules['sub_project_ids.*'] = ['integer', 'distinct', Rule::exists('sub_projects', 'id')
            ->where('project_id', $this->project->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $linked))];

        return $rules;
    }

    /**
     * La séance qu'on modifie : elle ne se chevauche pas elle-même.
     */
    protected function ignoredSessionId(): ?int
    {
        return $this->scheduled->id;
    }
}
