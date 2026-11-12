<?php

namespace App\Http\Requests\Funders;

use App\Rules\UniqueFunderName;

/**
 * Renommer un financeur, changer sa catégorie, le désactiver ou le réactiver (#249).
 */
class UpdateFunderRequest extends StoreFunderRequest
{
    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['bail', 'sometimes', 'required', 'string', 'max:150', new UniqueFunderName($this->ignoreId())];
        $rules['is_active'] = ['sometimes', 'required', 'boolean'];

        return $rules;
    }

    /**
     * Le financeur qu'on modifie : son propre nom ne compte pas comme un doublon.
     */
    protected function ignoreId(): ?int
    {
        return (int) $this->route('funder')->id;
    }
}
