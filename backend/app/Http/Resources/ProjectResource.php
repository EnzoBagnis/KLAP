<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_type' => $this->project_type,
            'planned_sessions' => $this->planned_sessions,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'default_location' => $this->whenLoaded('defaultLocation', fn () => [
                'id' => $this->defaultLocation->id,
                'name' => $this->defaultLocation->name,
            ]),
            'sub_projects_count' => $this->whenCounted('subProjects'),
            'sub_projects' => SubProjectResource::collection($this->whenLoaded('subProjects')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
