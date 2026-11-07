<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduledSessionResource;
use App\Models\LogbookEntry;
use App\Models\ScheduledSession;
use App\Models\User;
use App\Services\StatisticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord (#146), page d'accueil de tous les rôles : une seule requête, les droits
 * restent côté serveur. Fiches à remplir, prochaines séances, dernières fiches rendues et,
 * pour l'administration, le suivi des fiches manquantes et des demandes de réouverture.
 */
class DashboardController extends Controller
{
    private const UPCOMING = 5;

    private const RECENT = 5;

    /** Relations d'une séance au planning (ScheduledSessionResource), échéance de la fiche en plus. */
    private const SESSION_RELATIONS = ['project', 'location', 'referent', 'subProjects',
        'logbookEntry:id,scheduled_session_id,status,edit_deadline'];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $manager = $user->hasRole(UserRole::Admin, UserRole::SuperUser);

        return response()->json([
            'to_fill' => $this->toFill($user)->map(fn (ScheduledSession $session) => [
                ...(new ScheduledSessionResource($session))->resolve(),
                // Dernier jour pour rendre la fiche : J+10, ou l'échéance d'un brouillon rouvert.
                'deadline' => ($session->logbookEntry?->edit_deadline
                    ?? $session->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS))->toDateString(),
            ])->all(),
            'upcoming' => ScheduledSessionResource::collection($this->upcoming($user))->resolve(),
            'recent_entries' => $this->recentEntries($user),
            'follow_up' => $manager ? [
                ...(new StatisticsService)->missingSummary(),
                // Seule l'administratrice rouvre une fiche : la super-utilisatrice n'a rien à traiter.
                'unlock_requests' => $user->hasRole(UserRole::Admin) ? $this->pendingUnlockRequests() : null,
            ] : null,
        ]);
    }

    /**
     * Séances dont je suis référent·e, du jour ou passées, encore dans le délai, sans fiche
     * ou avec un brouillon. Les plus anciennes d'abord : ce sont les plus urgentes.
     */
    private function toFill(User $user): Collection
    {
        $today = LogbookEntry::localNow()->toDateString();
        $oldest = LogbookEntry::localNow()->subDays(LogbookEntry::EDIT_DAYS)->toDateString();

        return ScheduledSession::query()
            ->where('referent_id', $user->id)
            ->where('status', '<>', 'cancelled')
            ->whereHas('project', fn (Builder $project) => $project->where('status', '<>', 'archived'))
            ->whereDate('session_date', '<=', $today)
            ->where(fn (Builder $open) => $open
                // Pas encore de fiche : elle s'ouvre jusqu'à J+10.
                ->where(fn (Builder $none) => $none->doesntHave('logbookEntry')->whereDate('session_date', '>=', $oldest))
                // Un brouillon, dans son délai (prolongé s'il a été rouvert).
                ->orWhereHas('logbookEntry', fn (Builder $entry) => $entry->where('status', 'draft')
                    ->whereDate('edit_deadline', '>=', $today)))
            ->with(self::SESSION_RELATIONS)
            ->orderBy('session_date')->orderBy('start_time')
            ->get();
    }

    /** Prochaines séances que je vois au planning, à partir d'aujourd'hui : ni annulées ni d'un projet archivé. */
    private function upcoming(User $user)
    {
        return ScheduledSession::query()
            ->visibleTo($user)
            ->where('status', '<>', 'cancelled')
            ->whereHas('project', fn (Builder $project) => $project->where('status', '<>', 'archived'))
            ->whereDate('session_date', '>=', LogbookEntry::localNow()->toDateString())
            ->with(self::SESSION_RELATIONS)
            ->orderBy('session_date')->orderBy('start_time')
            ->limit(self::UPCOMING)
            ->get();
    }

    /** Dernières fiches rendues que je peux lire (droits emboîtés, #141). */
    private function recentEntries(User $user): array
    {
        return LogbookEntry::query()
            ->readableBy($user)
            ->whereIn('status', ['submitted', 'locked'])
            ->with(['author:id,name', 'scheduledSession.project:id,name', 'scheduledSession.subProjects:id,name'])
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->limit(self::RECENT)
            ->get()
            ->map(fn (LogbookEntry $entry) => [
                'id' => $entry->id,
                'status' => $entry->status,
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
                'author' => $entry->author ? ['id' => $entry->author->id, 'name' => $entry->author->name] : null,
                'session' => [
                    'id' => $entry->scheduledSession->id,
                    'session_date' => $entry->scheduledSession->session_date->toDateString(),
                    'project' => ['id' => $entry->scheduledSession->project->id, 'name' => $entry->scheduledSession->project->name],
                    'sub_projects' => $entry->scheduledSession->subProjects->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values(),
                ],
            ])
            ->all();
    }

    /**
     * Fiches dont la dernière demande de réouverture est postérieure au dernier déverrouillage,
     * et que l'administratrice peut encore rouvrir (séance non annulée, projet non archivé).
     */
    private function pendingUnlockRequests(): int
    {
        return LogbookEntry::query()
            ->whereHas('scheduledSession', fn (Builder $session) => $session->where('status', '<>', 'cancelled')
                ->whereHas('project', fn (Builder $project) => $project->where('status', '<>', 'archived')))
            ->whereHas('unlockRequests', fn (Builder $request) => $request->where(fn (Builder $pending) => $pending
                ->whereNull('logbook_entries.unlocked_at')
                ->orWhereColumn('klap_notifications.created_at', '>', 'logbook_entries.unlocked_at')))
            ->count();
    }
}
