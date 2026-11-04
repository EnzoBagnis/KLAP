<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funder;
use Illuminate\Http\JsonResponse;

/**
 * Financeurs proposés dans le menu « Financeur destinataire » d'un projet.
 * Pas de filtre : la table n'a pas de notion de financeur actif (décision de #90).
 */
class FunderController extends Controller
{
    public function index(): JsonResponse
    {
        $funders = Funder::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json(['data' => $funders]);
    }
}
