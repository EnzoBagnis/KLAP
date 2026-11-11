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
 * Financeurs (#249), gérés par l'administratrice : création, renommage, désactivation.
 * Pas de suppression : un financeur désactivé n'est plus proposé pour un nouveau projet,
 * mais les projets qui l'ont le gardent (et leur pré-bilan aussi).
 */
class FunderController extends Controller
{
    private const FIELDS = ['id', 'name', 'type', 'is_active'];

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

    public function store(StoreFunderRequest $request): JsonResponse
    {
        $funder = $this->saving(fn () => Funder::query()->create(['type' => 'other', ...$request->validated(), 'is_active' => true]));

        return response()->json(['data' => $funder->fresh()->only(self::FIELDS)], 201);
    }

    public function update(UpdateFunderRequest $request, Funder $funder): JsonResponse
    {
        $this->saving(fn () => $funder->update($request->validated()));

        return response()->json(['data' => $funder->fresh()->only(self::FIELDS)]);
    }

    /** Deux enregistrements simultanés du même nom : le second bute sur l'index unique. */
    private function saving(callable $write)
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ce financeur existe déjà.']);
        }
    }
}
