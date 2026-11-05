<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur des statistiques et indicateurs pour KLAP Kelemenis & cie.
 * Implémente la règle de calcul impérative de Mariette Travard :
 * Effectif sous-projet = MAX(participants des séances)
 * Effectif total projet = SOMME(MAX des sous-projets)
 * (Jamais de somme brute des séances pour éviter de compter plusieurs fois les mêmes élèves).
 */
class StatisticsController extends Controller
{
    /**
     * Calcule et renvoie les statistiques globales d'un projet spécifique.
     */
    public function getProjectStats(int $projectId, Request $request): JsonResponse
    {
        $project = Project::with(['subProjects', 'funders', 'leadArtist'])->findOrFail($projectId);

        // 1. Calcul des effectifs dédoublonnés (MAX par classe)
        $subProjectMetrics = DB::table('v_sub_project_participants')
            ->where('project_id', $projectId)
            ->get();

        $totalMaxParticipants = (int) (DB::table('v_project_participants')
            ->where('project_id', $projectId)
            ->value('total_participants_max') ?? 0);

        // 2. Statistiques des séances
        $sessions = ScheduledSession::where('project_id', $projectId)->get();
        $totalSessions = $sessions->count();

        $sessionIds = $sessions->pluck('id');
        $entries = LogbookEntry::whereIn('scheduled_session_id', $sessionIds)->get();
        $completedEntriesCount = $entries->whereIn('status', ['submitted', 'locked'])->count();
        $draftEntriesCount = $entries->where('status', 'draft')->count();

        // 3. Calcul du volume horaire total réalisé
        $totalMinutes = 0;
        foreach ($sessions as $session) {
            if ($session->start_time && $session->end_time) {
                $start = Carbon::parse($session->start_time);
                $end = Carbon::parse($session->end_time);
                $totalMinutes += max(0, $end->diffInMinutes($start));
            }
        }
        $totalHours = round($totalMinutes / 60, 1);

        // 4. Répartition Garçons / Filles
        $totalMale = $entries->sum('attendees_male');
        $totalFemale = $entries->sum('attendees_female');

        // 5. Moyennes des évaluations 1-5 sur les participants
        $entryIds = $entries->pluck('id');
        $evaluations = ParticipantEvaluation::whereIn('logbook_entry_id', $entryIds)->get();

        $avgEngagement = $evaluations->avg('engagement') ? round($evaluations->avg('engagement'), 2) : null;
        $avgExpression = $evaluations->avg('expression') ? round($evaluations->avg('expression'), 2) : null;
        $avgAutonomy = $evaluations->avg('autonomy') ? round($evaluations->avg('autonomy'), 2) : null;

        // 6. Tendances de progression
        $progressionCounts = [
            'first_session' => $evaluations->where('progress', 'first_session')->count(),
            'improvement' => $evaluations->where('progress', 'improvement')->count(),
            'stagnation' => $evaluations->where('progress', 'stagnation')->count(),
            'regression' => $evaluations->where('progress', 'regression')->count(),
        ];

        // 7. Détail par sous-projet / classe
        $classesBreakdown = $project->subProjects->map(function (SubProject $sp) use ($subProjectMetrics) {
            $metric = $subProjectMetrics->firstWhere('sub_project_id', $sp->id);

            return [
                'id' => $sp->id,
                'name' => $sp->name,
                'organization' => $sp->organization,
                'class_group' => $sp->class_group,
                'age_range' => $sp->age_range,
                'unique_participants_max' => (int) ($metric->unique_participants_max ?? 0),
                'total_sessions_logged' => (int) ($metric->sessions_count ?? 0),
            ];
        });

        // 8. Extraits qualitatifs pour le bilan (Ce qui fonctionne, retours)
        $qualitativeHighlights = $entries->whereNotNull('what_works')
            ->filter(fn($e) => trim((string) $e->what_works) !== '')
            ->map(fn($e) => [
                'session_date' => $e->scheduledSession?->session_date?->format('d/m/Y'),
                'what_works' => $e->what_works,
                'additional_notes' => $e->additional_notes,
            ])
            ->values();

        $lead = $project->leadArtist->first();

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'lead_artist' => $lead ? $lead->name : null,
                'funders' => $project->funders->pluck('name'),
            ],
            'participants' => [
                'total_max' => $totalMaxParticipants,
                'total_male_logged' => $totalMale,
                'total_female_logged' => $totalFemale,
            ],
            'sessions' => [
                'total_planned' => $totalSessions,
                'completed' => $completedEntriesCount,
                'draft' => $draftEntriesCount,
                'total_hours' => $totalHours,
            ],
            'evaluations_averages' => [
                'engagement' => $avgEngagement,
                'expression' => $avgExpression,
                'autonomy' => $avgAutonomy,
            ],
            'progression_trends' => $progressionCounts,
            'classes_breakdown' => $classesBreakdown,
            'qualitative_highlights' => $qualitativeHighlights,
        ]);
    }

    /**
     * Renvoie la liste récapitulative de tous les projets avec leurs effectifs dédoublonnés.
     */
    public function getProjectsSummary(Request $request): JsonResponse
    {
        $projects = Project::with(['leadArtist'])
            ->withCount('scheduledSessions')
            ->orderBy('name')
            ->get();

        $metrics = DB::table('v_project_participants')->pluck('total_participants_max', 'project_id');

        $result = $projects->map(function ($p) use ($metrics) {
            $lead = $p->leadArtist->first();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'lead_artist' => $lead ? $lead->name : null,
                'sessions_count' => $p->scheduled_sessions_count,
                'total_participants_max' => (int) ($metrics[$p->id] ?? 0),
            ];
        });

        return response()->json($result);
    }
}
