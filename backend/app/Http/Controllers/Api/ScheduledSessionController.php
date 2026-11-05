<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sessions\ListSessionsRequest;
use App\Http\Requests\Sessions\StoreScheduledSessionRequest;
use App\Http\Resources\ScheduledSessionResource;
use App\Models\Project;
use App\Models\ScheduledSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/** Planning : séances programmées (#102). */
class ScheduledSessionController extends Controller
{
    private const RELATIONS = ['project', 'location', 'referent', 'subProjects'];

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
            ->when(! $user->hasRole(UserRole::Admin, UserRole::SuperUser), function (Builder $query) use ($user) {
                if (! $user->hasRole(UserRole::Contributor)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->where(fn (Builder $mine) => $mine
                    ->where('referent_id', $user->id)
                    ->orWhereHas('project', fn (Builder $project) => $project->visibleTo($user)));
            })
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
}
