<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes par l'administratrice (#159) : liste, désactivation, réactivation.
 * Un compte n'est jamais supprimé (il garde ses fiches et ses séances). Désactivé, il perd
 * aussitôt ses jetons et l'accès (#148).
 */
class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = User::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->payload($user));

        return response()->json(['data' => $accounts]);
    }

    public function update(Request $request, string $account): JsonResponse
    {
        abort_unless(ctype_digit($account), 404);
        $data = $request->validate(
            ['is_active' => ['required', 'boolean']],
            ['is_active.*' => 'Indiquez si le compte doit être actif ou non.'],
        );

        $user = DB::transaction(function () use ($request, $account, $data) {
            $user = User::query()->lockForUpdate()->find($account);
            abort_if($user === null, 404);
            if (! $data['is_active']) {
                $this->ensureCanDeactivate($request->user(), $user);
            }
            $user->update(['is_active' => $data['is_active']]);

            return $user;
        });

        return response()->json(['data' => $this->payload($user)]);
    }

    /**
     * Pas de désactivation de soi-même. Cela suffit à garder une administratrice active :
     * celle qui agit l'est forcément (un compte inactif est refusé, #148).
     */
    private function ensureCanDeactivate(User $actor, User $user): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['is_active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
