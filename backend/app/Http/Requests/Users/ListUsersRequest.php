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
            // On exige l'un ou l'autre : un menu ne propose jamais tous les comptes par oubli.
            'role' => ['required_without:equipe', Rule::enum(UserRole::class)],
            // Les comptes qu'on peut mettre dans une équipe : tous les actifs (#272).
            'equipe' => ['sometimes', 'accepted'],
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
            'role.required_without' => 'Précisez le rôle des comptes à lister.',
            'role' => 'Le rôle demandé n’existe pas.',
            'equipe' => 'Le filtre demandé n’existe pas.',
        ];
    }
}
