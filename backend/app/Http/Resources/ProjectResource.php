<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce qu'on renvoie d'un projet. Les relations (type, lieu, groupes, équipe, financeurs) ne
 * sortent que si le contrôleur les a chargées : la liste reste légère, le détail est complet.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
                'member_role' => $member->pivot->getAttribute('member_role'),
                // Renseignée quand on a retiré la personne de l'équipe (#251) : elle garde la
                // lecture de ses fiches, mais on ne lui confie plus de séance.
                'end_date' => $member->pivot->getAttribute('end_date'),
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
