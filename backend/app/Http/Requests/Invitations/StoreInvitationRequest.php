<?php

namespace App\Http\Requests\Invitations;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Inviter quelqu'un (administratrice, route role:admin). Rôles proposés : intervenant·e ou
 * super-utilisatrice ; inviter une autre administratrice est une décision de la cliente.
 * Une invitation en cours pour la même adresse est remplacée (mail perdu, faute de frappe) :
 * sans liste ni annulation, refuser bloquerait l'adresse jusqu'à l'expiration.
 */
class StoreInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            // 190 : même limite que la connexion.
            'email' => ['required', 'string', 'email', 'max:190'],
            'role' => ['required', Rule::in([UserRole::Contributor->value, UserRole::SuperUser->value])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('email')) {
                    return;
                }
                if (User::query()->whereRaw('LOWER(email) = ?', [$this->input('email')])->exists()) {
                    $validator->errors()->add('email', 'Un compte existe déjà avec cette adresse.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Indiquez le nom de la personne invitée.',
            'full_name.max' => 'Le nom ne doit pas dépasser 160 caractères.',
            'email.required' => 'Indiquez l’adresse mail.',
            'email.email' => 'L’adresse mail n’est pas valide.',
            'email.max' => 'L’adresse mail ne doit pas dépasser 190 caractères.',
            'role.required' => 'Choisissez un rôle.',
            'role.in' => 'Le rôle choisi n’est pas proposé.',
        ];
    }
}
