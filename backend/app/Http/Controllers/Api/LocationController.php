<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

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
}
