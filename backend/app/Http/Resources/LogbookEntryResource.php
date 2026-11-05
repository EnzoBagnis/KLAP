<?php

namespace App\Http\Resources;

use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogbookEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->scheduledSession;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'edit_deadline' => $this->edit_deadline?->toDateString(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'editable' => $this->author_id === $request->user()?->id
                && $this->status !== 'locked' && ! $this->deadlinePassed(),
            'author' => ['id' => $this->author->id, 'name' => $this->author->name],
            // Section 1, héritée de la séance (non modifiable depuis la fiche).
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'start_time' => substr((string) $session->start_time, 0, 5),
                'end_time' => substr((string) $session->end_time, 0, 5),
                'session_type' => $session->session_type,
                'session_type_detail' => $session->session_type_detail,
                'project' => ['id' => $session->project->id, 'name' => $session->project->name],
                'location' => ['id' => $session->location->id, 'name' => $session->location->name],
                'sub_projects' => $session->subProjects
                    ->map(fn ($group) => ['id' => $group->id, 'name' => $group->name, 'attendees' => $group->pivot->attendees])
                    ->values(),
            ],
            ...$this->resource->only(LogbookEntry::FIELDS),
            'evaluation' => $this->evaluation?->only(ParticipantEvaluation::FIELDS)
                ?? array_fill_keys(ParticipantEvaluation::FIELDS, null),
            'exercises' => $this->exercises->pluck('label')->values(),
        ];
    }
}
