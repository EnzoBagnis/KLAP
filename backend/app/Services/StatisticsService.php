<?php

namespace App\Services;

use App\Models\LogbookEntry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
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
            ->get(['id', 'project_id', 'name', 'age_range', 'city', 'postal_code', 'expected_headcount'])
            ->groupBy('project_id');

        $rows = $projects->map(function ($project) use ($groups, $indicators, $documented, $missing, $subProjects) {
            $children = ($subProjects[$project->id] ?? collect())
                ->map(fn ($group) => $this->groupRow($group, $groups[$group->id] ?? null))
                ->values();
            $indicator = $indicators[$project->id] ?? null;

            return [
                'id' => $project->id,
                'name' => $project->name,
                'participants' => $children->sum('participants'),
                'sessions_documented' => (int) ($documented[$project->id] ?? 0),
                ...$this->missingCounts($missing->where('project_id', $project->id)),
                ...$this->averages($indicator),
                'sub_projects' => $children,
            ];
        })->values();

        $allGroups = $rows->flatMap(fn ($row) => $row['sub_projects'])->where('participants', '>', 0);

        return [
            'filters' => ['project_id' => $this->projectId, 'from' => $this->from, 'to' => $this->to],
            'totals' => [
                'participants' => $rows->sum('participants'),
                'sessions_documented' => $rows->sum('sessions_documented'),
                ...$this->missingCounts($missing),
                // Moyennes sur toutes les évaluations, pas une moyenne des moyennes par projet.
                ...$this->averages($this->indicators(grouped: false)->first()),
            ],
            // Provenance (ville et code postal, pas l'arrondissement) et âge, portés par le sous-projet.
            'by_city' => $this->breakdown($allGroups, fn ($group) => [$group['city'], $group['postal_code']], ['city', 'postal_code']),
            // Tranche d'âge saisie librement : « 16 - 17 ans » et « 16-17 ans » sont regroupées.
            'by_age_range' => $this->breakdown($allGroups, fn ($group) => [self::ageRange($group['age_range'])], ['age_range']),
            'projects' => $rows,
        ];
    }

    /**
     * Ligne d'un groupe. Assiduité (choix d'équipe, à confirmer avec la cliente) : présents
     * moyens sur les séances documentées, rapportés à l'effectif prévu du groupe.
     */
    private function groupRow(object $group, ?object $stats): array
    {
        $raw = $stats?->average_attendees === null ? null : (float) $stats->average_attendees;
        $expected = $group->expected_headcount === null ? null : (int) $group->expected_headcount;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'age_range' => $group->age_range,
            'city' => $group->city,
            'postal_code' => $group->postal_code,
            'expected_headcount' => $expected,
            'participants' => (int) ($stats->participants ?? 0),
            'sessions_documented' => (int) ($stats->sessions_documented ?? 0),
            'average_attendees' => $raw === null ? null : round($raw, 1),
            // Calculé sur la moyenne exacte ; peut dépasser 100 % si plus de présents que prévu.
            'attendance_rate' => $raw !== null && $expected > 0 ? (int) round($raw / $expected * 100) : null,
        ];
    }

    private static function ageRange(?string $value): ?string
    {
        $value = $value === null ? '' : preg_replace(['/\s*-\s*/', '/\s+/'], ['-', ' '], trim($value));

        return $value === '' ? null : $value;
    }

    /** Participants regroupés par clé (ville et code postal, ou tranche d'âge), du plus grand au plus petit. */
    private function breakdown(Collection $groups, callable $key, array $fields): array
    {
        return $groups
            ->groupBy(fn ($group) => json_encode($key($group)))
            ->map(fn (Collection $same) => [
                ...array_combine($fields, $key($same->first())),
                'participants' => $same->sum('participants'),
                'groups' => $same->count(),
            ])
            ->sortByDesc('participants')
            ->values()
            ->all();
    }

    /** Fiches manquantes (toutes, en retard, encore à remplir) : mêmes chiffres que l'écran Statistiques. */
    public function missingSummary(): array
    {
        return $this->missingCounts($this->missingEntries());
    }

    /**
     * Fiches manquantes, en retard (échéance passée : J+10, ou nouvelle échéance après
     * réouverture d'un brouillon) ou à remplir (encore dans le délai).
     */
    private function missingCounts(Collection $missing): array
    {
        $today = LogbookEntry::localNow()->toDateString();
        $overdue = $missing->filter(function ($row) use ($today) {
            $deadline = $row->edit_deadline !== null
                ? substr((string) $row->edit_deadline, 0, 10)
                : Carbon::parse(substr((string) $row->session_date, 0, 10))->addDays(LogbookEntry::EDIT_DAYS)->toDateString();

            return $deadline < $today;
        })->count();

        return [
            'entries_missing' => $missing->count(),
            'entries_overdue' => $overdue,
            'entries_to_fill' => $missing->count() - $overdue,
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
            ->selectRaw('sp.sub_project_id, MAX(sp.attendees) AS participants, AVG(sp.attendees) AS average_attendees, '
                .'COUNT(DISTINCT sp.scheduled_session_id) AS sessions_documented')
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

    /**
     * v_missing_entries, filtrée : séances passées (heure de Paris) sans fiche rendue,
     * avec l'échéance du brouillon s'il y en a un (une seule fiche par séance).
     */
    private function missingEntries(): Collection
    {
        return $this->scoped(DB::table('scheduled_sessions as s'))
            ->leftJoin('logbook_entries as draft', 'draft.scheduled_session_id', '=', 's.id')
            ->whereDate('s.session_date', '<', LogbookEntry::localNow()->toDateString())
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('logbook_entries as le')
                ->whereColumn('le.scheduled_session_id', 's.id')
                ->whereIn('le.status', self::RENDERED))
            ->get(['s.id', 's.project_id', 's.session_date', 'draft.edit_deadline']);
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
