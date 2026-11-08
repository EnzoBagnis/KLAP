<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_date' => $this->session_date?->toDateString(),
            // « HH:MM » sur tous les moteurs (SQLite et MariaDB ne renvoient pas le même format).
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'session_type' => $this->session_type,
            'session_type_detail' => $this->session_type_detail,
            'status' => $this->status,
            // Statut du projet : une séance d'un projet archivé n'ouvre plus de fiche (#152).
            'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name, 'status' => $this->project->status]),
            'location' => $this->whenLoaded('location', fn () => ['id' => $this->location->id, 'name' => $this->location->name]),
            'referent' => $this->whenLoaded('referent', fn () => ['id' => $this->referent->id, 'name' => $this->referent->name]),
            'sub_projects' => $this->whenLoaded('subProjects', fn () => $this->subProjects
                ->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values()),
            // Fiche de la séance si elle existe : lien « Consulter » au planning (#141).
            'logbook_entry' => $this->whenLoaded('logbookEntry', fn () => $this->logbookEntry
                ? ['id' => $this->logbookEntry->id, 'status' => $this->logbookEntry->status]
                : null),
        ];
    }
}
