<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ListSessionsRequest;
use App\Http\Requests\Sessions\StoreScheduledSessionRequest;
use App\Http\Requests\Sessions\UpdateScheduledSessionRequest;
use App\Http\Resources\ScheduledSessionResource;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planning : les séances programmées (#102). L'administratrice les crée, les modifie, les
 * annule et les rétablit (#180) ; tout le monde les lit, selon ses droits.
 */
class ScheduledSessionController extends Controller
{
    /** Les relations qu'on renvoie avec une séance ; de la fiche, on ne prend que le statut. */
    private const RELATIONS = ['project', 'location', 'referent', 'subProjects', 'logbookEntry:id,scheduled_session_id,status'];

    /**
     * On liste les séances d'un projet, si l'utilisateur peut voir ce projet (RG02) ; sinon 404.
     *
     * @param  string  $project  l'identifiant du projet, tel qu'il arrive dans l'URL
     */
    public function indexForProject(Request $request, string $project): AnonymousResourceCollection
    {
        $found = ctype_digit($project)
            ? Project::query()->visibleTo($request->user())->whereKey($project)->first()
            : null;
        abort_if($found === null, 404);

        $sessions = ScheduledSession::query()
            ->where('project_id', $found->id)
            ->with(self::RELATIONS)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return ScheduledSessionResource::collection($sessions);
    }

    /**
     * On renvoie le planning d'une période. Administratrice et super-utilisatrice : tout.
     * Intervenant : les séances de ses projets, plus celles dont il est référent. On inclut
     * les séances annulées, avec leur statut.
     */
    public function index(ListSessionsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sessions = ScheduledSession::query()
            // On compare sur la date seule : sous SQLite, la colonne contient aussi l'heure (00:00:00).
            ->whereDate('session_date', '>=', $request->validated('from'))
            ->whereDate('session_date', '<=', $request->validated('to'))
            ->visibleTo($user)
            ->with(self::RELATIONS)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return ScheduledSessionResource::collection($sessions);
    }

    /**
     * On programme une séance (administratrice). Elle naît « prévue », et on note qui l'a créée.
     */
    public function store(StoreScheduledSessionRequest $request): JsonResponse
    {
        $project = $request->project();
        $data = $request->validated();

        // Trois essais : si une autre requête a écrit entre-temps, MariaDB demande de rejouer la
        // transaction (« try restarting transaction ») ; au second essai, on voit son écriture.
        $session = DB::transaction(function () use ($project, $data, $request) {
            $this->ensureReferentFree($data);
            $session = new ScheduledSession(collect($data)->except('sub_project_ids')->all());
            $session->project_id = $project->id;
            $session->status = 'planned';
            $session->created_by = $request->user()->id;
            $session->save();

            $session->subProjects()->attach($this->groupRows($data['sub_project_ids'], $project->id));

            return $session;
        }, attempts: 3);

        return (new ScheduledSessionResource($session->fresh(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * On modifie une séance qui n'a pas encore de fiche (#180) : on remplace ses champs et
     * ses groupes.
     */
    public function update(UpdateScheduledSessionRequest $request): ScheduledSessionResource
    {
        $data = $request->validated();

        // Trois essais : si une autre requête a écrit entre-temps, MariaDB demande de rejouer la
        // transaction (« try restarting transaction ») ; au second essai, on voit son écriture.
        $session = DB::transaction(function () use ($request, $data) {
            // On relit la séance en la verrouillant : une fiche ou une annulation arrivée
            // depuis les contrôles de la requête bloque la modification.
            $session = $this->lockedSession((string) $request->scheduledSession()->id);
            abort_if($session->status === 'cancelled', 409, 'Cette séance vient d’être annulée.');
            abort_if($session->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche : elle ne se modifie plus.');
            $this->ensureReferentFree($data, $session->id);
            $session->fill(collect($data)->except('sub_project_ids')->all());
            // Le détail ne sert qu'au type « autre » : on l'efface si le type a changé.
            if ($session->session_type !== 'other') {
                $session->session_type_detail = null;
            }
            $session->save();
            $session->subProjects()->sync($this->groupRows($data['sub_project_ids'], $session->project_id));

            return $session;
        }, attempts: 3);

        return new ScheduledSessionResource($session->fresh(self::RELATIONS));
    }

    /**
     * On annule une séance (#180), seulement si elle n'a pas de fiche. On n'efface rien.
     *
     * @param  string  $session  l'identifiant de la séance, tel qu'il arrive dans l'URL
     */
    public function cancel(Request $request, string $session): ScheduledSessionResource
    {
        $found = DB::transaction(function () use ($session) {
            $found = $this->lockedSession($session);
            abort_if($found->status === 'cancelled', 409, 'Cette séance est déjà annulée.');
            // Un projet archivé est figé (#241) : on refuse, comme pour la modification et le
            // rétablissement.
            abort_if($found->project->status === 'archived', 409, 'Ce projet est archivé : sa séance ne s’annule plus.');
            abort_if($found->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche : elle ne s’annule plus.');
            $found->forceFill(['status' => 'cancelled'])->save();

            return $found;
        });

        return new ScheduledSessionResource($found->fresh(self::RELATIONS));
    }

    /**
     * On rétablit une séance annulée (#180), si l'intervenant·e n'a rien d'autre à ce moment-là.
     *
     * @param  string  $session  l'identifiant de la séance, tel qu'il arrive dans l'URL
     *
     * @throws ValidationException si l'intervenant·e n'est plus disponible pour cette séance
     */
    public function restore(Request $request, string $session): ScheduledSessionResource
    {
        $found = DB::transaction(function () use ($session) {
            $found = $this->lockedSession($session);
            abort_unless($found->status === 'cancelled', 409, 'Cette séance n’est pas annulée.');
            abort_if($found->project->status === 'archived', 409, 'Ce projet est archivé : sa séance ne se rétablit pas.');
            // Le ou la référent·e doit pouvoir remplir la fiche : on vérifie que son compte est
            // actif et qu'il ou elle est affecté·e au projet à cette date.
            $referentOk = DB::table('users')->where('id', $found->referent_id)->where('is_active', true)->exists()
                && DB::table('project_user')->where('project_id', $found->project_id)->where('user_id', $found->referent_id)
                    ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $found->session_date))
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $found->session_date))
                    ->exists();
            if (! $referentOk) {
                throw ValidationException::withMessages(['session' => 'L’intervenant·e de cette séance n’est plus actif·ve ou plus affecté·e au projet à cette date : programmez plutôt une nouvelle séance.']);
            }
            $clash = ScheduledSession::overlapping($found->referent_id, $found->session_date->toDateString(),
                (string) $found->start_time, (string) $found->end_time, $found->id);
            if ($clash !== null) {
                throw ValidationException::withMessages(['session' => ScheduledSession::overlapMessage($clash)]);
            }
            $found->forceFill(['status' => 'planned'])->save();

            return $found;
        });

        return new ScheduledSessionResource($found->fresh(self::RELATIONS));
    }

    /**
     * On revérifie le chevauchement dans la transaction, après avoir verrouillé la ligne de
     * l'intervenant·e : la requête l'a déjà contrôlé, mais deux programmations du même créneau
     * arrivées en même temps (double appui, deux onglets) passaient toutes les deux ce contrôle.
     * Le verrou les fait passer l'une après l'autre ; la seconde voit la séance de la première.
     *
     * @param  array<string, mixed>  $data  la saisie validée
     * @param  int|null  $ignoredId  la séance qu'on modifie, qui ne se chevauche pas elle-même
     *
     * @throws ValidationException si l'intervenant·e a déjà une séance sur ce créneau
     */
    private function ensureReferentFree(array $data, ?int $ignoredId = null): void
    {
        User::query()->whereKey($data['referent_id'])->lockForUpdate()->first();
        $clash = ScheduledSession::overlapping((int) $data['referent_id'], (string) $data['session_date'],
            (string) $data['start_time'], (string) $data['end_time'], $ignoredId, latest: true);
        if ($clash !== null) {
            throw ValidationException::withMessages(['start_time' => ScheduledSession::overlapMessage($clash)]);
        }
    }

    /**
     * On relit la séance en la verrouillant, dans la transaction de l'appelant ; 404 si elle
     * n'existe pas.
     *
     * @param  string  $id  l'identifiant de la séance, tel qu'il arrive dans l'URL
     */
    private function lockedSession(string $id): ScheduledSession
    {
        abort_unless(ctype_digit($id), 404);

        return ScheduledSession::query()->with('project')->lockForUpdate()->findOrFail($id);
    }

    /**
     * On prépare les lignes du pivot séance–groupe. On y répète le projet : la base s'en sert
     * pour garantir que tous les groupes d'une séance sont du même projet.
     *
     * @param  list<int>  $groupIds
     * @return array<int, array{project_id: int}>
     */
    private function groupRows(array $groupIds, int $projectId): array
    {
        return array_fill_keys($groupIds, ['project_id' => $projectId]);
    }
}
