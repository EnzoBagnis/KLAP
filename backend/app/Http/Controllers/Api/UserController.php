<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ListUsersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Les comptes qu'on propose dans les menus (par exemple l'intervenant·e principal·e d'un
 * projet). Ce n'est pas un écran de gestion des comptes : on ne sort que l'identifiant et
 * le nom, et on ne propose que les comptes actifs.
 */
class UserController extends Controller
{
    /**
     * On liste les comptes actifs du rôle demandé, par nom.
     */
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
