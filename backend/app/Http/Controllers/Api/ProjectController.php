<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ArchiveProjectRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Projets. On réserve la création à l'administratrice ; la lecture est ouverte à la
 * super-utilisatrice (tout) et aux intervenants (leurs projets), selon RG02 (#55, #89).
 * L'administratrice modifie, archive et restaure (#152) : on n'efface jamais un projet
 * (règle de la cliente).
 */
class ProjectController extends Controller
{
    /** Les relations qu'on renvoie avec le détail d'un projet et avec la réponse de création. */
    private const DETAIL_RELATIONS = ['defaultLocation', 'projectType', 'subProjects', 'members', 'funders'];

    /**
     * On liste les projets visibles. On masque les projets archivés par défaut : pour
     * l'administratrice, archiver revient à supprimer. `?include_archived=1` les réaffiche.
     * Un intervenant ne voit que les projets où il est affecté (RG02).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->when(! $request->boolean('include_archived'), fn ($query) => $query->where('status', '!=', 'archived'))
            ->with(['defaultLocation', 'projectType'])
            ->withCount('subProjects')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * On cherche le projet parmi ceux que l'utilisateur peut voir, sans liaison de route : un
     * projet non affecté et un projet inexistant donnent exactement la même 404, qui ne révèle
     * pas qu'un projet existe (#89).
     *
     * @param  string  $project  l'identifiant du projet, tel qu'il arrive dans l'URL
     */
    public function show(Request $request, string $project): ProjectResource
    {
        $found = Project::query()
            ->visibleTo($request->user())
            ->with(self::DETAIL_RELATIONS)
            ->whereKey($project)
            ->first();

        abort_if($found === null, 404);

        return new ProjectResource($found);
    }

    /**
     * On crée le projet avec ses groupes, son intervenant·e principal·e et ses financeurs,
     * dans une seule transaction : en cas d'erreur, on ne garde rien.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();

        $project = DB::transaction(function () use ($data, $request) {
            $project = new Project(collect($data)->only([
                'name', 'description', 'project_type_id', 'planned_sessions',
                'start_date', 'end_date', 'status', 'default_location_id',
            ])->all());
            $project->created_by = $request->user()->id;
            $project->save();

            // Tout projet a au moins un sous-projet, même quand il n'est pas découpé :
            // sans liste, on crée un sous-projet unique qui porte le nom du projet.
            $subProjects = $data['sub_projects'] ?? [];
            if ($subProjects === []) {
                $subProjects = [['name' => $project->name] + collect($data)->only(['age_range', 'expected_headcount'])->all()];
            }

            // La structure, la ville et le code postal saisis sur le projet nous servent de
            // valeurs par défaut pour chaque sous-projet qui ne donne pas les siens.
            $defaults = collect($data)->only(['organization', 'city', 'postal_code'])->filter()->all();

            foreach ($subProjects as $subProject) {
                $project->subProjects()->create(array_merge($defaults, array_filter(
                    $subProject,
                    fn ($value) => $value !== null,
                )));
            }

            // À la création, on n'affecte que l'intervenant·e principal·e ; les ponctuel·le·s
            // s'ajoutent ensuite depuis la page du projet (#251).
            $project->members()->attach($data['principal_user_id'], ['member_role' => 'lead']);
            $project->funders()->attach($data['funder_ids'] ?? []);

            return $project;
        });

        // Avec fresh(), on relit le statut par défaut que la base pose quand il n'est pas fourni.
        return (new ProjectResource($project->fresh(self::DETAIL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * On modifie une partie du projet (#152). Un·e nouvel·le intervenant·e principal·e remplace
     * l'ancien·ne, qui reste membre en ponctuel·le pour garder la lecture de ses fiches. Les
     * financeurs envoyés remplacent la liste.
     */
    public function update(UpdateProjectRequest $request): ProjectResource
    {
        $data = $request->validated();

        $project = DB::transaction(function () use ($request, $data) {
            // On relit le projet en le verrouillant : un changement de statut ne peut pas
            // écraser un archivage fait au même moment.
            $project = $this->lockedOrConflict($request->project()->id, archived: false, message: 'Ce projet est archivé : restaurez-le avant de le modifier.');
            $formerName = $project->name;
            $project->fill(collect($data)->only([
                'name', 'description', 'project_type_id', 'planned_sessions',
                'start_date', 'end_date', 'status', 'default_location_id',
            ])->all())->save();

            // Projet non découpé : son sous-projet unique, qu'on a créé au nom du projet, suit
            // le renommage.
            if ($project->wasChanged('name') && $project->subProjects()->where('is_active', true)->count() === 1) {
                $project->subProjects()->where('name', $formerName)->update(['name' => $project->name]);
            }
            if (array_key_exists('principal_user_id', $data)) {
                $lead = $data['principal_user_id'];
                $project->members()->newPivotQuery()->where('member_role', 'lead')->where('user_id', '<>', $lead)
                    ->update(['member_role' => 'occasional']);
                // Le ou la principal·e l'est sur toute la durée du projet : on efface ses
                // éventuelles dates de ponctuel·le.
                $project->members()->syncWithoutDetaching([$lead => ['member_role' => 'lead', 'start_date' => null, 'end_date' => null]]);
            }
            if (array_key_exists('funder_ids', $data)) {
                $project->funders()->sync($data['funder_ids'] ?? []);
            }

            return $project;
        });

        return new ProjectResource($project->fresh(self::DETAIL_RELATIONS));
    }

    /**
     * On archive le projet, après confirmation par son nom (#152). On conserve ses séances,
     * ses fiches et ses statistiques.
     */
    public function archive(ArchiveProjectRequest $request): ProjectResource
    {
        $project = DB::transaction(function () use ($request) {
            $project = $this->lockedOrConflict($request->project()->id, archived: false, message: 'Ce projet est déjà archivé.');
            $project->forceFill(['status' => 'archived', 'archived_at' => now()])->save();

            return $project;
        });

        return new ProjectResource($project->fresh(self::DETAIL_RELATIONS));
    }

    /**
     * On restaure un projet archivé, en « actif » par défaut (#152).
     *
     * @param  string  $project  l'identifiant du projet, tel qu'il arrive dans l'URL
     */
    public function restore(Request $request, string $project): ProjectResource
    {
        abort_unless(ctype_digit($project) && Project::query()->whereKey($project)->exists(), 404);
        $data = $request->validate(
            ['status' => ['sometimes', 'required', Rule::in(Project::EDITABLE_STATUSES)]],
            ['status.*' => 'Choisissez le statut du projet restauré : brouillon, actif ou terminé.'],
        );

        $found = DB::transaction(function () use ($project, $data) {
            $found = $this->lockedOrConflict((int) $project, archived: true, message: 'Ce projet n’est pas archivé.');
            $found->forceFill(['status' => $data['status'] ?? 'active', 'archived_at' => null])->save();

            return $found;
        });

        return new ProjectResource($found->fresh(self::DETAIL_RELATIONS));
    }

    /**
     * On relit le projet en le verrouillant dans la transaction ; on répond 409 s'il n'est pas
     * (ou plus) dans l'état attendu.
     *
     * @param  bool  $archived  l'état attendu : archivé ou non
     * @param  string  $message  le message du 409
     */
    private function lockedOrConflict(int $id, bool $archived, string $message): Project
    {
        $project = Project::query()->lockForUpdate()->findOrFail($id);
        abort_if(($project->status === 'archived') !== $archived, 409, $message);

        return $project;
    }
}
