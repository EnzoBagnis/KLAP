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
 * retirer (désactiver) ou remettre. On ne propose plus un groupe retiré pour une nouvelle
 * séance, mais on garde ses séances, ses fiches et ses chiffres. Un projet archivé ne bouge plus.
 */
class SubProjectController extends Controller
{
    /**
     * On ajoute un groupe au projet.
     */
    public function store(StoreSubProjectRequest $request): JsonResponse
    {
        // Trois essais : si une autre requête a écrit entre-temps, MariaDB demande de rejouer la
        // transaction (« try restarting transaction ») ; au second essai, on voit son écriture.
        $group = DB::transaction(function () use ($request) {
            $project = $this->editable($request->project()->id);
            $this->ensureNameFree($project->id, (string) $request->validated('name'));

            return $project->subProjects()->create([...$request->validated(), 'is_active' => true]);
        }, attempts: 3);

        return (new SubProjectResource($group->fresh()))->response()->setStatusCode(201);
    }

    /**
     * On corrige un groupe, on le retire ou on le remet.
     *
     * @throws ValidationException si on retirait le dernier groupe actif du projet
     */
    public function update(UpdateSubProjectRequest $request): SubProjectResource
    {
        $data = $request->validated();
        // « 0 », « false », false… : la règle boolean accepte plusieurs écritures, on n'en garde qu'une.
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }
        // Trois essais : si une autre requête a écrit entre-temps, MariaDB demande de rejouer la
        // transaction (« try restarting transaction ») ; au second essai, on voit son écriture.
        $group = DB::transaction(function () use ($request, $data) {
            $this->editable($request->project()->id);
            $group = SubProject::query()->lockForUpdate()->findOrFail($request->group()->id);
            if (array_key_exists('name', $data)) {
                $this->ensureNameFree($group->project_id, (string) $data['name'], $group->id);
            }
            if (($data['is_active'] ?? true) === false && $group->is_active) {
                $others = SubProject::query()->where('project_id', $group->project_id)->whereKeyNot($group->id)->where('is_active', true)->exists();
                if (! $others) {
                    throw ValidationException::withMessages(['is_active' => 'Un projet garde au moins un groupe actif : ajoutez-en un autre avant de retirer celui-ci.']);
                }
            }
            $group->update($data);

            return $group;
        }, attempts: 3);

        return new SubProjectResource($group->fresh());
    }

    /**
     * On relit le projet en le verrouillant : un projet archivé, même à l'instant, refuse
     * toute modification.
     */
    private function editable(int $id): Project
    {
        $project = Project::query()->lockForUpdate()->findOrFail($id);
        abort_if($project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de modifier ses groupes.');

        return $project;
    }

    /**
     * On revérifie le nom une fois le projet verrouillé : la requête l'a déjà contrôlé, mais deux
     * ajouts du même groupe arrivés en même temps passaient tous les deux ce contrôle. Aucun
     * index ne peut le garantir en base : on compare sans tenir compte de la casse.
     *
     * @param  int|null  $ignoreId  le groupe qu'on modifie, dont le propre nom n'est pas un doublon
     *
     * @throws ValidationException si le projet a déjà un groupe de ce nom
     */
    private function ensureNameFree(int $projectId, string $name, ?int $ignoreId = null): void
    {
        $taken = SubProject::query()->where('project_id', $projectId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            // Lecture verrouillante : elle lit l'état validé le plus récent, pas l'instantané de
            // la transaction, qui peut dater d'avant l'ajout de l'autre requête.
            ->lockForUpdate()
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Ce projet a déjà un groupe de ce nom.']);
        }
    }
}
