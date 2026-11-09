<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ListSessionsRequest;
use App\Http\Requests\Sessions\StoreScheduledSessionRequest;
use App\Http\Requests\Sessions\UpdateScheduledSessionRequest;
use App\Http\Resources\ScheduledSessionResource;
use App\Models\Project;
use App\Models\ScheduledSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Planning : séances programmées (#102). */
class ScheduledSessionController extends Controller
{
    private const RELATIONS = ['project', 'location', 'referent', 'subProjects', 'logbookEntry:id,scheduled_session_id,status'];

    /** Séances d'un projet, si l'utilisateur peut voir ce projet (RG02) ; sinon 404. */
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
     * Planning sur une période. Administratrice et super-utilisatrice : tout.
     * Intervenant : les séances de ses projets, plus celles dont il est référent.
     * Les séances annulées sont incluses, avec leur statut.
     */
    public function index(ListSessionsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sessions = ScheduledSession::query()
            // Comparaison sur la date seule : sous SQLite, la colonne contient aussi l'heure (00:00:00).
            ->whereDate('session_date', '>=', $request->validated('from'))
            ->whereDate('session_date', '<=', $request->validated('to'))
            ->visibleTo($user)
            ->with(self::RELATIONS)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return ScheduledSessionResource::collection($sessions);
    }

    /** Programmer une séance (administratrice). Statut « prévue », auteur = utilisateur connecté. */
    public function store(StoreScheduledSessionRequest $request): JsonResponse
    {
        $project = $request->project();
        $data = $request->validated();

        $session = DB::transaction(function () use ($project, $data, $request) {
            $session = new ScheduledSession(collect($data)->except('sub_project_ids')->all());
            $session->project_id = $project->id;
            $session->status = 'planned';
            $session->created_by = $request->user()->id;
            $session->save();

            $session->subProjects()->attach(
                collect($data['sub_project_ids'])->mapWithKeys(fn ($id) => [$id => ['project_id' => $project->id]])->all()
            );

            return $session;
        });

        return (new ScheduledSessionResource($session->fresh(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /** Modifier une séance sans fiche (#180) : champs et groupes remplacés. */
    public function update(UpdateScheduledSessionRequest $request): ScheduledSessionResource
    {
        $data = $request->validated();

        $session = DB::transaction(function () use ($request, $data) {
            // Relue verrouillée : une fiche ou une annulation arrivée depuis les contrôles bloque la modification.
            $session = $this->lockedSession((string) $request->scheduledSession()->id);
            abort_if($session->status === 'cancelled', 409, 'Cette séance vient d’être annulée.');
            abort_if($session->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche : elle ne se modifie plus.');
            $session->fill(collect($data)->except('sub_project_ids')->all());
            // Un détail ne survit pas à un changement de type.
            if ($session->session_type !== 'other') {
                $session->session_type_detail = null;
            }
            $session->save();
            $session->subProjects()->sync(
                collect($data['sub_project_ids'])->mapWithKeys(fn ($id) => [$id => ['project_id' => $session->project_id]])->all()
            );

            return $session;
        });

        return new ScheduledSessionResource($session->fresh(self::RELATIONS));
    }

    /** Annuler une séance (#180), seulement si elle n'a pas de fiche. Rien n'est effacé. */
    public function cancel(Request $request, string $session): ScheduledSessionResource
    {
        $found = DB::transaction(function () use ($session) {
            $found = $this->lockedSession($session);
            abort_if($found->status === 'cancelled', 409, 'Cette séance est déjà annulée.');
            abort_if($found->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche : elle ne s’annule plus.');
            $found->forceFill(['status' => 'cancelled'])->save();

            return $found;
        });

        return new ScheduledSessionResource($found->fresh(self::RELATIONS));
    }

    /** Rétablir une séance annulée (#180), si l'intervenant·e n'a rien d'autre à ce moment-là. */
    public function restore(Request $request, string $session): ScheduledSessionResource
    {
        $found = DB::transaction(function () use ($session) {
            $found = $this->lockedSession($session);
            abort_unless($found->status === 'cancelled', 409, 'Cette séance n’est pas annulée.');
            abort_if($found->project->status === 'archived', 409, 'Ce projet est archivé : sa séance ne se rétablit pas.');
            // Le ou la référent·e doit pouvoir remplir la fiche : compte actif, affecté au projet à cette date.
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

    /** Séance relue verrouillée, dans la transaction de l'appelant ; 404 si elle n'existe pas. */
    private function lockedSession(string $id): ScheduledSession
    {
        abort_unless(ctype_digit($id), 404);

        return ScheduledSession::query()->with('project')->lockForUpdate()->findOrFail($id);
    }
}
