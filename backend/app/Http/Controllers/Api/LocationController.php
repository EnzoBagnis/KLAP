<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lieux qu'on propose dans les menus (projet, séance, fiche). C'est ouvert à tous les
 * rôles : tout utilisateur peut choisir un lieu, et en ajouter un (contexte client : les
 * projets en tournée). On ne propose que les lieux actifs, comme le veut la validation de
 * la création d'un projet.
 */
class LocationController extends Controller
{
    /** Ce qu'on expose d'un lieu dans l'écran de gestion de l'administratrice. */
    private const ADMIN_FIELDS = ['id', 'name', 'address', 'postal_code', 'city', 'is_klap', 'is_active'];

    /**
     * On liste les lieux actifs, pour les menus. Avec `?tous=1`, l'administratrice reçoit la
     * liste complète, lieux désactivés compris, pour sa gestion (#249).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('tous') && $request->user()->isAdmin()) {
            $all = Location::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(self::ADMIN_FIELDS);

            return response()->json(['data' => $all]);
        }

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'postal_code', 'city']);

        return response()->json(['data' => $locations]);
    }

    /**
     * On corrige, on désactive ou on réactive un lieu (#249). On n'efface rien.
     *
     * @throws ValidationException si un autre lieu porte déjà ce nom pour ce code postal
     */
    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        try {
            $location->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ce lieu existe déjà pour ce code postal.']);
        }

        return response()->json(['data' => $location->fresh()->only(self::ADMIN_FIELDS)]);
    }

    /**
     * On crée un lieu, actif, et jamais marqué « lieu de KLAP » : les trois studios viennent
     * du seeder.
     *
     * @throws ValidationException si le lieu existe déjà
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        try {
            $location = Location::query()->create([
                ...$request->validated(),
                'is_klap' => false,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Si deux personnes créent le même lieu en même temps, la seconde bute sur l'index unique.
            throw ValidationException::withMessages(['name' => 'Ce lieu existe déjà pour ce code postal.']);
        }

        return response()->json(['data' => $location->only(['id', 'name', 'postal_code', 'city'])], 201);
    }
}
