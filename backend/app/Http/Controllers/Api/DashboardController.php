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
 * Tableau de bord (#146), la page d'accueil de tous les rôles. On répond en une seule
 * requête, et on garde les droits côté serveur : fiches à remplir, prochaines séances,
 * dernières fiches rendues et, pour l'administration, le suivi des fiches manquantes et des
 * demandes de réouverture.
 */
class DashboardController extends Controller
{
    /** Le nombre de prochaines séances qu'on affiche. */
    private const UPCOMING = 5;

    /** Le nombre de dernières fiches rendues qu'on affiche. */
    private const RECENT = 5;

    /** Les relations d'une séance au planning (ScheduledSessionResource) ; on y ajoute l'échéance de la fiche. */
    private const SESSION_RELATIONS = ['project', 'location', 'referent', 'subProjects',
        'logbookEntry:id,scheduled_session_id,status,edit_deadline'];

    /**
     * On assemble le tableau de bord de la personne connectée.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $manager = $user->hasRole(UserRole::Admin, UserRole::SuperUser);

        return response()->json([
            'to_fill' => $this->toFill($user)->map(fn (ScheduledSession $session) => [
                ...(new ScheduledSessionResource($session))->resolve(),
                // Le dernier jour pour rendre la fiche : celui de la séance (J+10, ou la date de
                // rattrapage), ou l'échéance d'un brouillon qu'on a rouvert.
                'deadline' => ($session->logbookEntry->edit_deadline
                    ?? LogbookEntry::deadlineFor($session->session_date))->toDateString(),
            ])->all(),
            'upcoming' => ScheduledSessionResource::collection($this->upcoming($user))->resolve(),
            'recent_entries' => $this->recentEntries($user),
            'follow_up' => $manager ? [
                ...(new StatisticsService)->missingSummary(),
                // Seule l'administratrice rouvre une fiche : on ne montre pas ce compteur à la
                // super-utilisatrice, qui n'a rien à traiter.
                'unlock_requests' => $user->hasRole(UserRole::Admin) ? $this->pendingUnlockRequests() : null,
            ] : null,
        ]);
    }

    /**
     * Les séances dont on est référent·e, du jour ou passées, encore dans le délai, sans fiche
     * ou avec un brouillon. On met les plus anciennes d'abord : ce sont les plus urgentes.
     *
     * @return Collection<int, ScheduledSession>
     */
    private function toFill(User $user): Collection
    {
        $today = LogbookEntry::localNow()->toDateString();
        $oldest = LogbookEntry::localNow()->subDays(LogbookEntry::EDIT_DAYS)->toDateString();
        // Pendant le rattrapage (#269), une séance ancienne sans fiche reste à remplir.
        $catchingUp = LogbookEntry::catchUpUntil()?->toDateString() >= $today;

        return ScheduledSession::query()
            ->where('referent_id', $user->id)
            ->where('status', '<>', 'cancelled')
            ->whereHas('project', fn (Builder $project) => $project->where('status', '<>', 'archived'))
            ->whereDate('session_date', '<=', $today)
            ->where(fn (Builder $open) => $open
                // Pas encore de fiche : on peut l'ouvrir jusqu'à J+10, ou sans limite pendant le rattrapage.
                ->where(fn (Builder $none) => $none->doesntHave('logbookEntry')
                    ->when(! $catchingUp, fn (Builder $recent) => $recent->whereDate('session_date', '>=', $oldest)))
                // Un brouillon, dans son délai (prolongé si on l'a rouvert).
                ->orWhereHas('logbookEntry', fn (Builder $entry) => $entry->where('status', 'draft')
                    ->whereDate('edit_deadline', '>=', $today)))
            ->with(self::SESSION_RELATIONS)
            ->orderBy('session_date')->orderBy('start_time')
            ->get();
    }

    /**
     * Les prochaines séances qu'on voit au planning, à partir d'aujourd'hui : ni annulées, ni
     * d'un projet archivé.
     *
     * @return Collection<int, ScheduledSession>
     */
    private function upcoming(User $user): Collection
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

    /**
     * Les dernières fiches rendues qu'on peut lire (droits emboîtés, #141).
     *
     * @return list<array<string, mixed>>
     */
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
                'author' => ['id' => $entry->author->id, 'name' => $entry->author->name],
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
     * On compte les fiches dont la dernière demande de réouverture est postérieure au dernier
     * déverrouillage, et que l'administratrice peut encore rouvrir (séance non annulée, projet
     * non archivé).
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
