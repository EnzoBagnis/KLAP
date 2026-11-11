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
 * Création des projets réservée à l'administratrice ; lecture ouverte à la
 * super-utilisatrice (tout) et aux intervenants (leurs projets), selon RG02 (#55, #89).
 * Modification, archivage et restauration par l'administratrice (#152) : un projet n'est
 * jamais effacé (règle de la cliente).
 */
class ProjectController extends Controller
{
    /** Relations renvoyées par le détail et par la réponse de création. */
    private const DETAIL_RELATIONS = ['defaultLocation', 'projectType', 'subProjects', 'members', 'funders'];

    /**
     * Les projets archivés sont masqués par défaut : pour l'administratrice, archiver
     * revient à supprimer. `?include_archived=1` les réaffiche. Un intervenant ne voit
     * que les projets où il est affecté (RG02).
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
     * Le projet est cherché parmi ceux que l'utilisateur peut voir, sans liaison de route :
     * un projet non affecté et un projet inexistant donnent exactement la même 404, qui ne
     * révèle pas qu'un projet existe (#89).
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
            // sans liste, un sous-projet unique porte le nom du projet.
            $subProjects = $data['sub_projects'] ?? [];
            if ($subProjects === []) {
                $subProjects = [['name' => $project->name] + collect($data)->only(['age_range', 'expected_headcount'])->all()];
            }

            // Structure, ville et code postal saisis sur le projet servent de valeurs
            // par défaut à chaque sous-projet qui ne donne pas les siens.
            $defaults = collect($data)->only(['organization', 'city', 'postal_code'])->filter()->all();

            foreach ($subProjects as $subProject) {
                $project->subProjects()->create(array_merge($defaults, array_filter(
                    $subProject,
                    fn ($value) => $value !== null,
                )));
            }

            // Seul·e l'intervenant·e principal·e est affecté·e à la création.
            $project->members()->attach($data['principal_user_id'], ['member_role' => 'lead']);
            $project->funders()->attach($data['funder_ids'] ?? []);

            return $project;
        });

        // fresh() relit le statut par défaut posé par la base quand il n'est pas fourni.
        return (new ProjectResource($project->fresh(self::DETAIL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modification partielle (#152). Un·e nouvel·le intervenant·e principal·e remplace l'ancien·ne,
     * qui reste membre en ponctuel·le pour garder la lecture de ses fiches. Les financeurs
     * envoyés remplacent la liste.
     */
    public function update(UpdateProjectRequest $request): ProjectResource
    {
        $data = $request->validated();

        $project = DB::transaction(function () use ($request, $data) {
            // Relu verrouillé : un archivage concurrent ne peut pas être écrasé par un changement de statut.
            $project = $this->lockedOrConflict($request->project()->id, archived: false, message: 'Ce projet est archivé : restaurez-le avant de le modifier.');
            $formerName = $project->name;
            $project->fill(collect($data)->only([
                'name', 'description', 'project_type_id', 'planned_sessions',
                'start_date', 'end_date', 'status', 'default_location_id',
            ])->all())->save();

            // Projet non découpé : son sous-projet unique, créé au nom du projet, suit le renommage.
            if ($project->wasChanged('name') && $project->subProjects()->where('is_active', true)->count() === 1) {
                $project->subProjects()->where('name', $formerName)->update(['name' => $project->name]);
            }
            if (array_key_exists('principal_user_id', $data)) {
                $lead = $data['principal_user_id'];
                $project->members()->newPivotQuery()->where('member_role', 'lead')->where('user_id', '<>', $lead)
                    ->update(['member_role' => 'occasional']);
                // Principal·e sur toute la durée du projet : d'éventuelles dates de ponctuel·le sont effacées.
                $project->members()->syncWithoutDetaching([$lead => ['member_role' => 'lead', 'start_date' => null, 'end_date' => null]]);
            }
            if (array_key_exists('funder_ids', $data)) {
                $project->funders()->sync($data['funder_ids'] ?? []);
            }

            return $project;
        });

        return new ProjectResource($project->fresh(self::DETAIL_RELATIONS));
    }

    /** Archivage confirmé par le nom (#152) : séances, fiches et statistiques sont conservées. */
    public function archive(ArchiveProjectRequest $request): ProjectResource
    {
        $project = DB::transaction(function () use ($request) {
            $project = $this->lockedOrConflict($request->project()->id, archived: false, message: 'Ce projet est déjà archivé.');
            $project->forceFill(['status' => 'archived', 'archived_at' => now()])->save();

            return $project;
        });

        return new ProjectResource($project->fresh(self::DETAIL_RELATIONS));
    }

    /** Restauration d'un projet archivé, en « actif » par défaut (#152). */
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

    /** Projet relu verrouillé dans la transaction ; 409 s'il n'est pas (ou plus) dans l'état attendu. */
    private function lockedOrConflict(int $id, bool $archived, string $message): Project
    {
        $project = Project::query()->lockForUpdate()->findOrFail($id);
        abort_if(($project->status === 'archived') !== $archived, 409, $message);

        return $project;
    }
}
