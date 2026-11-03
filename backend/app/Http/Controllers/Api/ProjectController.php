<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Création et lecture des projets, réservées à l'administratrice (RG02, #55).
 * La modification et l'archivage relèvent d'autres issues.
 */
class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with('defaultLocation')
            ->withCount('subProjects')
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load(['defaultLocation', 'subProjects']));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();

        $project = DB::transaction(function () use ($data, $request) {
            $project = new Project(collect($data)->only([
                'name', 'description', 'project_type', 'planned_sessions',
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

            return $project;
        });

        // fresh() relit le statut par défaut posé par la base quand il n'est pas fourni.
        return (new ProjectResource($project->fresh(['defaultLocation', 'subProjects'])))
            ->response()
            ->setStatusCode(201);
    }
}
