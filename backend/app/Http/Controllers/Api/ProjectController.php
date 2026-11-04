<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Création et lecture des projets, réservées à l'administratrice (RG02, #55).
 * La modification et l'archivage relèvent d'autres issues.
 */
class ProjectController extends Controller
{
    /** Relations renvoyées par le détail et par la réponse de création. */
    private const DETAIL_RELATIONS = ['defaultLocation', 'projectType', 'subProjects', 'members', 'funders'];

    /**
     * Les projets archivés sont masqués par défaut : pour l'administratrice, archiver
     * revient à supprimer. `?include_archived=1` les réaffiche.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->when(! $request->boolean('include_archived'), fn ($query) => $query->where('status', '!=', 'archived'))
            ->with(['defaultLocation', 'projectType'])
            ->withCount('subProjects')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load(self::DETAIL_RELATIONS));
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
                $subProjects = [['name' => $project->name]];
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
}
