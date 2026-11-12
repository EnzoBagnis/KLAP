<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lister les comptes d'un rôle, pour remplir un menu.
 */
class ListUsersRequest extends FormRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // On l'exige : un menu ne doit jamais proposer tous les comptes, administratrices comprises.
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Précisez le rôle des comptes à lister.',
            'role' => 'Le rôle demandé n’existe pas.',
        ];
    }
}
