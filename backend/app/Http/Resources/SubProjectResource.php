<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubProjectResource extends JsonResource
{
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
