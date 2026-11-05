<?php

namespace App\Http\Requests\Invitations;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Inviter quelqu'un (administratrice, route role:admin). Rôles proposés : intervenant·e ou
 * super-utilisatrice ; inviter une autre administratrice est une décision de la cliente.
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
            'email' => ['required', 'string', 'email', 'max:255'],
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
                $email = $this->input('email');
                if (User::query()->where('email', $email)->exists()) {
                    $validator->errors()->add('email', 'Un compte existe déjà avec cette adresse.');

                    return;
                }
                $pending = Invitation::query()->pending()->where('email', $email)->first();
                if ($pending) {
                    $validator->errors()->add('email', 'Une invitation est déjà en cours pour cette adresse, jusqu’au '
                        .$pending->expires_at->timezone('Europe/Paris')->format('d/m/Y').'.');
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
            'role.required' => 'Choisissez un rôle.',
            'role.in' => 'Le rôle choisi n’est pas proposé.',
        ];
    }
}
