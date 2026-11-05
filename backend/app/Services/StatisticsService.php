<?php

namespace App\Services;

use App\Models\LogbookEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques à partir des fiches rendues (submitted, locked), sans rien stocker.
 * Même calcul que les vues v_sub_project_participants, v_project_participants,
 * v_project_indicators et v_missing_entries, qui ne se filtrent pas par période
 * (pas de colonne de date) : ici, le SQL des vues est repris avec les filtres.
 *
 * Participants d'un sous-projet = maximum des présents sur ses séances ;
 * participants d'un projet = somme de ces maximums (jamais un cumul).
 */
class StatisticsService
{
    private const RENDERED = ['submitted', 'locked'];

    public function __construct(
        private readonly ?int $projectId = null,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    public function compute(): array
    {
        $groups = $this->subProjectParticipants();
        $indicators = $this->indicators()->keyBy('project_id');
        $documented = $this->documentedSessions();
        $missing = $this->missingEntries();

        $projects = DB::table('projects')
            ->when($this->projectId, fn (Builder $query, $id) => $query->where('id', $id))
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name']);
        $subProjects = DB::table('sub_projects')
            ->whereIn('project_id', $projects->pluck('id'))
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'project_id', 'name'])
            ->groupBy('project_id');

        $rows = $projects->map(function ($project) use ($groups, $indicators, $documented, $missing, $subProjects) {
            $children = ($subProjects[$project->id] ?? collect())->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'participants' => (int) ($groups[$group->id]->participants ?? 0),
                'sessions_documented' => (int) ($groups[$group->id]->sessions_documented ?? 0),
            ])->values();
            $indicator = $indicators[$project->id] ?? null;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'participants' => $children->sum('participants'),
                'sessions_documented' => (int) ($documented[$project->id] ?? 0),
                'entries_missing' => (int) ($missing[$project->id] ?? 0),
                ...$this->averages($indicator),
                'sub_projects' => $children,
            ];
        })->values();

        return [
            'filters' => ['project_id' => $this->projectId, 'from' => $this->from, 'to' => $this->to],
            'totals' => [
                'participants' => $rows->sum('participants'),
                'sessions_documented' => $rows->sum('sessions_documented'),
                'entries_missing' => $rows->sum('entries_missing'),
                // Moyennes sur toutes les évaluations, pas une moyenne des moyennes par projet.
                ...$this->averages($this->indicators(grouped: false)->first()),
            ],
            'projects' => $rows,
        ];
    }

    /** Séances non annulées du périmètre (projet, période). */
    private function scoped(Builder $query, string $session = 's'): Builder
    {
        return $query
            ->where("{$session}.status", '<>', 'cancelled')
            ->when($this->projectId, fn (Builder $q, $id) => $q->where("{$session}.project_id", $id))
            ->when($this->from, fn (Builder $q, $from) => $q->whereDate("{$session}.session_date", '>=', $from))
            ->when($this->to, fn (Builder $q, $to) => $q->whereDate("{$session}.session_date", '<=', $to));
    }

    /** v_sub_project_participants, filtrée. */
    private function subProjectParticipants(): Collection
    {
        return $this->scoped(DB::table('scheduled_session_sub_project as sp')
            ->join('logbook_entries as le', 'le.scheduled_session_id', '=', 'sp.scheduled_session_id')
            ->join('scheduled_sessions as s', 's.id', '=', 'sp.scheduled_session_id')
            ->whereIn('le.status', self::RENDERED))
            ->groupBy('sp.sub_project_id')
            ->selectRaw('sp.sub_project_id, MAX(sp.attendees) AS participants, COUNT(DISTINCT sp.scheduled_session_id) AS sessions_documented')
            ->get()
            ->keyBy('sub_project_id');
    }

    /** Séances documentées (fiche rendue) par projet. */
    private function documentedSessions(): Collection
    {
        return $this->scoped(DB::table('logbook_entries as le')
            ->join('scheduled_sessions as s', 's.id', '=', 'le.scheduled_session_id')
            ->whereIn('le.status', self::RENDERED))
            ->groupBy('s.project_id')
            ->selectRaw('s.project_id, COUNT(DISTINCT s.id) AS sessions')
            ->pluck('sessions', 'project_id');
    }

    /** v_project_indicators, filtrée ; sans regroupement, une ligne pour tout le périmètre. */
    private function indicators(bool $grouped = true): Collection
    {
        $query = $this->scoped(DB::table('logbook_entries as le')
            ->join('scheduled_sessions as s', 's.id', '=', 'le.scheduled_session_id')
            ->join('participant_evaluations as pe', 'pe.logbook_entry_id', '=', 'le.id')
            ->whereIn('le.status', self::RENDERED))
            ->selectRaw('COUNT(*) AS evaluations, ROUND(AVG(pe.engagement), 2) AS avg_engagement, '
                .'ROUND(AVG(pe.expression), 2) AS avg_expression, ROUND(AVG(pe.autonomy), 2) AS avg_autonomy');

        return $grouped ? $query->addSelect('s.project_id')->groupBy('s.project_id')->get() : $query->get();
    }

    /** v_missing_entries, filtrée : séances passées (heure de Paris) sans fiche rendue. */
    private function missingEntries(): Collection
    {
        return $this->scoped(DB::table('scheduled_sessions as s'))
            ->whereDate('s.session_date', '<', LogbookEntry::localNow()->toDateString())
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('logbook_entries as le')
                ->whereColumn('le.scheduled_session_id', 's.id')
                ->whereIn('le.status', self::RENDERED))
            ->groupBy('s.project_id')
            ->selectRaw('s.project_id, COUNT(*) AS missing')
            ->pluck('missing', 'project_id');
    }

    /** Moyennes sur 5 (null sans aucune note) et nombre d'évaluations. */
    private function averages(?object $row): array
    {
        $average = fn (string $key) => $row?->{$key} === null ? null : round((float) $row->{$key}, 2);

        return [
            'evaluations' => (int) ($row->evaluations ?? 0),
            'avg_engagement' => $average('avg_engagement'),
            'avg_expression' => $average('avg_expression'),
            'avg_autonomy' => $average('avg_autonomy'),
        ];
    }
}
