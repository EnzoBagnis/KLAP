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
            'project_type' => $this->whenLoaded('projectType', fn () => $this->projectType ? [
                'id' => $this->projectType->id,
                'name' => $this->projectType->name,
            ] : null),
            'planned_sessions' => $this->planned_sessions,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'default_location' => $this->whenLoaded('defaultLocation', fn () => [
                'id' => $this->defaultLocation->id,
                'name' => $this->defaultLocation->name,
            ]),
            'sub_projects_count' => $this->whenCounted('subProjects'),
            'sub_projects' => SubProjectResource::collection($this->whenLoaded('subProjects')),
            'team' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'member_role' => $member->pivot->member_role,
                // Retiré·e de l'équipe (#251) : garde la lecture de ses fiches, ne reçoit plus de séance.
                'end_date' => $member->pivot->end_date,
            ])->values()),
            'funders' => $this->whenLoaded('funders', fn () => $this->funders->map(fn ($funder) => [
                'id' => $funder->id,
                'name' => $funder->name,
                'type' => $funder->type,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
