<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Lieux proposés dans les menus (projet, séance, fiche). Ouvert à tous les rôles :
 * tout utilisateur peut choisir un lieu (contexte client). Seuls les lieux actifs
 * sont proposés, comme le veut la validation de la création d'un projet.
 */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'postal_code', 'city']);

        return response()->json(['data' => $locations]);
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
