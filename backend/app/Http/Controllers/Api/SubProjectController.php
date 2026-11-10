<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubProjects\StoreSubProjectRequest;
use App\Http\Requests\SubProjects\UpdateSubProjectRequest;
use App\Http\Resources\SubProjectResource;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Groupes d'un projet après sa création (#250), administratrice seulement : ajouter, corriger,
 * retirer (désactiver) ou remettre. Un groupe retiré n'est plus proposé pour une nouvelle
 * séance ; ses séances, ses fiches et ses chiffres restent. Un projet archivé ne bouge plus.
 */
class SubProjectController extends Controller
{
    public function store(StoreSubProjectRequest $request): JsonResponse
    {
        $group = DB::transaction(function () use ($request) {
            $project = $this->editable($request->project()->id);

            return $project->subProjects()->create([...$request->validated(), 'is_active' => true]);
        });

        return (new SubProjectResource($group->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateSubProjectRequest $request): SubProjectResource
    {
        $data = $request->validated();
        $group = DB::transaction(function () use ($request, $data) {
            $this->editable($request->project()->id);
            $group = SubProject::query()->lockForUpdate()->findOrFail($request->group()->id);
            if (($data['is_active'] ?? true) === false && $group->is_active) {
                $others = SubProject::query()->where('project_id', $group->project_id)->whereKeyNot($group->id)->where('is_active', true)->exists();
                if (! $others) {
                    throw ValidationException::withMessages(['is_active' => 'Un projet garde au moins un groupe actif : ajoutez-en un autre avant de retirer celui-ci.']);
                }
            }
            $group->update($data);

            return $group;
        });

        return new SubProjectResource($group->fresh());
    }

    /** Projet relu verrouillé : un projet archivé (même à l'instant) refuse toute modification. */
    private function editable(int $id): Project
    {
        $project = Project::query()->lockForUpdate()->findOrFail($id);
        abort_if($project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de modifier ses groupes.');

        return $project;
    }
}
