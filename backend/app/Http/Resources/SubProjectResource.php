<?php

namespace App\Http\Resources;

use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce qu'on renvoie d'un groupe (sous-projet).
 *
 * @mixin SubProject
 */
class SubProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organization' => $this->organization,
            'class_group' => $this->class_group,
            'age_range' => $this->age_range,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'expected_headcount' => $this->expected_headcount,
            'is_active' => $this->is_active,
        ];
    }
}
