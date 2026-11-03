<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur des lieux d'ateliers et résidences KLAP.
 * Conforme aux retours de Mariette Travard :
 * - Liste ordonnée en priorité avec les 3 studios de KLAP (Grand Studio, Studio 2, Studio 3).
 * - Création ouverte à tous les membres (avec validation obligatoire : Nom, Code postal 5 chiffres, Ville).
 */
class LocationController extends Controller
{
    /**
     * Liste tous les lieux d'activité.
     * Place les studios permanents de KLAP en tête de liste pour faciliter la saisie rapide.
     */
    public function index(Request $request): JsonResponse
    {
        $locations = Location::orderBy('is_klap', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($locations);
    }

    /**
     * Enregistre un nouveau lieu d'intervention.
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $location = Location::create([
            'name' => $validated['name'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'structure' => $validated['structure'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_klap' => $validated['is_klap'] ?? false,
        ]);

        return response()->json([
            'message' => 'Lieu ajouté avec succès.',
            'location' => $location,
        ], 201);
    }
}
