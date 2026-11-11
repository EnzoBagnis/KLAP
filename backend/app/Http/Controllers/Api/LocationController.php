<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lieux proposés dans les menus (projet, séance, fiche). Ouvert à tous les rôles :
 * tout utilisateur peut choisir un lieu (contexte client). Seuls les lieux actifs
 * sont proposés, comme le veut la validation de la création d'un projet.
 */
class LocationController extends Controller
{
    private const ADMIN_FIELDS = ['id', 'name', 'address', 'postal_code', 'city', 'is_klap', 'is_active'];

    public function index(Request $request): JsonResponse
    {
        // ?tous=1 : la liste complète, lieux désactivés compris, pour la gestion (#249, administratrice).
        if ($request->boolean('tous') && $request->user()->role === UserRole::Admin) {
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

    /** Corriger, désactiver ou réactiver un lieu (#249). Rien n'est effacé. */
    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        try {
            $location->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ce lieu existe déjà pour ce code postal.']);
        }

        return response()->json(['data' => $location->fresh()->only(self::ADMIN_FIELDS)]);
    }

    /** Nouveau lieu, actif, jamais marqué lieu de KLAP (les trois studios viennent du seeder). */
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
            // Deux créations simultanées du même lieu : la seconde bute sur l'index unique.
            throw ValidationException::withMessages(['name' => 'Ce lieu existe déjà pour ce code postal.']);
        }

        return response()->json(['data' => $location->only(['id', 'name', 'postal_code', 'city'])], 201);
    }
}
