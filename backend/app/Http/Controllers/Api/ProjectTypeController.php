<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectTypeRequest;
use App\Http\Requests\Projects\UpdateProjectTypeRequest;
use App\Models\ProjectType;
use Illuminate\Http\JsonResponse;

/**
 * Liste des types de projet, gérée par l'administratrice : création, renommage,
 * désactivation. Pas de suppression : un type désactivé n'est plus proposé à la
 * création d'un projet, mais les projets qui l'utilisent le gardent.
 */
class ProjectTypeController extends Controller
{
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

    public function store(StoreProjectTypeRequest $request): JsonResponse
    {
        $type = ProjectType::create($request->validated());

        return response()->json(['data' => $type->only(['id', 'name', 'is_active'])], 201);
    }

    public function update(UpdateProjectTypeRequest $request, ProjectType $projectType): JsonResponse
    {
        $projectType->update($request->validated());

        return response()->json(['data' => $projectType->only(['id', 'name', 'is_active'])]);
    }
}
