<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Obligatoire : un menu ne doit jamais proposer tous les comptes, admins compris.
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Précisez le rôle des comptes à lister.',
            'role' => 'Le rôle demandé n’existe pas.',
        ];
    }
}
