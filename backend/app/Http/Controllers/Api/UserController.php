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
     * On liste les comptes actifs, par nom : ceux du rôle demandé, ou, avec `equipe=1`, tous
     * ceux qu'on peut mettre dans l'équipe d'un projet (#272). L'administratrice intervient
     * elle aussi : le rôle décide des droits, pas de qui anime une séance.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->when(! $request->boolean('equipe'), fn ($query) => $query->where('role', $request->validated('role')))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }
}
