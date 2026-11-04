<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ListUsersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Comptes proposés dans les menus (par exemple l'intervenant·e principal·e d'un projet).
 * Ce n'est pas un écran de gestion des comptes : seuls l'identifiant et le nom sortent,
 * et seuls les comptes actifs sont proposés.
 */
class UserController extends Controller
{
    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->where('role', $request->validated('role'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }
}
