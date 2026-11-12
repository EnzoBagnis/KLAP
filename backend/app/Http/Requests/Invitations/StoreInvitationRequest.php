<?php

namespace App\Http\Requests\Invitations;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Inviter quelqu'un (administratrice, route role:admin). On propose deux rôles :
 * intervenant·e ou super-utilisatrice ; inviter une autre administratrice est une décision
 * de la cliente. On remplace une invitation en cours pour la même adresse (mail perdu, faute
 * de frappe) : sans liste ni annulation, refuser bloquerait l'adresse jusqu'à l'expiration.
 */
class StoreInvitationRequest extends FormRequest
{
    /**
     * On met l'adresse en minuscules et sans espaces autour, pour comparer avec les comptes existants.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            // 190 caractères : on garde la même limite qu'à la connexion.
            'email' => ['required', 'string', 'email', 'max:190'],
            'role' => ['required', Rule::in([UserRole::Contributor->value, UserRole::SuperUser->value])],
        ];
    }

    /**
     * Les contrôles qu'on fait après les règles : ceux qui croisent plusieurs champs ou qui
     * lisent la base.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('email')) {
                    return;
                }
                $existing = User::query()->whereRaw('LOWER(email) = ?', [$this->input('email')])->first(['id', 'is_active']);
                if ($existing !== null) {
                    // Pour un compte désactivé, on demande de le réactiver plutôt que de
                    // réinviter (#159). On peut le dire : la route est réservée à l'administratrice.
                    $validator->errors()->add('email', $existing->is_active
                        ? 'Un compte existe déjà avec cette adresse.'
                        : 'Un compte désactivé existe avec cette adresse : réactivez-le dans la liste des comptes.');
                }
            },
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
