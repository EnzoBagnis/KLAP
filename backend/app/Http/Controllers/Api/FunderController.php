<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funders\StoreFunderRequest;
use App\Http\Requests\Funders\UpdateFunderRequest;
use App\Models\Funder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Financeurs (#249), une liste que l'administratrice tient elle-même : création, renommage,
 * désactivation. On ne supprime jamais un financeur : désactivé, on ne le propose plus pour
 * un nouveau projet, mais les projets qui l'ont le gardent (et leur pré-bilan aussi).
 */
class FunderController extends Controller
{
    /** Ce qu'on expose d'un financeur. */
    private const FIELDS = ['id', 'name', 'type', 'is_active'];

    /**
     * On liste tous les financeurs, les actifs d'abord, avec le nombre de projets qu'ils financent.
     */
    public function index(): JsonResponse
    {
        $funders = Funder::query()
            ->select(self::FIELDS)
            ->withCount('projects')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $funders]);
    }

    /**
     * On crée un financeur, actif, de catégorie « autre » si on ne la précise pas.
     */
    public function store(StoreFunderRequest $request): JsonResponse
    {
        $funder = $this->saving(fn () => Funder::query()->create(['type' => 'other', ...$request->validated(), 'is_active' => true]));

        return response()->json(['data' => $funder->fresh()->only(self::FIELDS)], 201);
    }

    /**
     * On renomme un financeur, on change sa catégorie, on le désactive ou on le réactive.
     */
    public function update(UpdateFunderRequest $request, Funder $funder): JsonResponse
    {
        $this->saving(fn () => $funder->update($request->validated()));

        return response()->json(['data' => $funder->fresh()->only(self::FIELDS)]);
    }

    /**
     * On exécute une écriture en traduisant le doublon : si deux personnes enregistrent le
     * même nom en même temps, la seconde bute sur l'index unique.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     *
     * @throws ValidationException si le nom est déjà pris
     */
    private function saving(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ce financeur existe déjà.']);
        }
    }
}
