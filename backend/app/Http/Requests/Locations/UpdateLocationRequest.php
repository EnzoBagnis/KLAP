<?php

namespace App\Http\Requests\Locations;

use Illuminate\Validation\Rule;

/**
 * Corriger, désactiver ou réactiver un lieu (#249), administratrice seulement. Désactivé, un lieu
 * n'est plus proposé dans les menus ; les projets et séances qui l'utilisent le gardent.
 */
class UpdateLocationRequest extends StoreLocationRequest
{
    public function rules(): array
    {
        $location = $this->route('location');
        $postalCode = $this->input('postal_code', $location->postal_code);

        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:150',
                Rule::unique('locations')->ignore($location->id)->where('postal_code', is_string($postalCode) ? $postalCode : '')],
            'postal_code' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{5}$/'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'is_active.required' => 'Indiquez si le lieu est actif.',
            'is_active.boolean' => 'Indiquez si le lieu est actif.',
        ];
    }
}
