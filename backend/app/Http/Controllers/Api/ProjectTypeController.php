<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectTypeRequest;
use App\Http\Requests\Projects\UpdateProjectTypeRequest;
use App\Models\ProjectType;
use Illuminate\Http\JsonResponse;

/**
 * Types de projet, une liste que l'administratrice tient elle-même : création, renommage,
 * désactivation. On ne supprime jamais un type : désactivé, on ne le propose plus à la
 * création d'un projet, mais les projets qui l'utilisent le gardent.
 */
class ProjectTypeController extends Controller
{
    /**
     * On liste tous les types, les actifs d'abord, avec le nombre de projets qui les utilisent.
     */
    public function index(): JsonResponse
    {
        $types = ProjectType::query()
            ->select(['id', 'name', 'is_active'])
            ->withCount('projects')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $types]);
    }

    /**
     * On crée un type de projet.
     */
    public function store(StoreProjectTypeRequest $request): JsonResponse
    {
        $type = ProjectType::create($request->validated());

        return response()->json(['data' => $type->only(['id', 'name', 'is_active'])], 201);
    }

    /**
     * On renomme un type, on le désactive ou on le réactive.
     */
    public function update(UpdateProjectTypeRequest $request, ProjectType $projectType): JsonResponse
    {
        $projectType->update($request->validated());

        return response()->json(['data' => $projectType->only(['id', 'name', 'is_active'])]);
    }
}
