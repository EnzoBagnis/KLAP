<?php

namespace App\Http\Requests\Funders;

use App\Rules\UniqueFunderName;

/** Renommer, changer la catégorie, désactiver ou réactiver un financeur (#249). */
class UpdateFunderRequest extends StoreFunderRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['bail', 'sometimes', 'required', 'string', 'max:150', new UniqueFunderName($this->ignoreId())];
        $rules['is_active'] = ['sometimes', 'required', 'boolean'];

        return $rules;
    }

    protected function ignoreId(): ?int
    {
        return (int) $this->route('funder')->id;
    }
}
