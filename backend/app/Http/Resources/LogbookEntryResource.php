<?php

namespace App\Http\Resources;

use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce qu'on renvoie d'une fiche de séance : son contenu, sa séance, et ce que la personne
 * connectée a le droit d'en faire (modifier, rouvrir, demander la réouverture). Le front
 * n'a ainsi aucune règle de droits à recalculer.
 *
 * @mixin LogbookEntry
 */
class LogbookEntryResource extends JsonResource
{
    /**
     * On assemble la fiche pour la personne connectée.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->scheduledSession;
        // Séance annulée ou projet archivé : on ne modifie plus et on ne rouvre plus la fiche
        // (même règle que UpdateLogbookEntryRequest).
        $active = $session->status !== 'cancelled' && $session->project->status !== 'archived';
        $pendingRequest = $this->resource->pendingUnlockRequestAt();
        $user = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'edit_deadline' => $this->edit_deadline?->toDateString(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            // Modifiable par son auteur, l'administratrice ou la super-utilisatrice (#141), tant
            // qu'elle n'est pas verrouillée.
            'editable' => $this->resource->canBeEditedBy($user) && ! $this->isLocked() && $active,
            // L'administratrice peut la rouvrir si elle est verrouillée ou hors délai, et que
            // la séance et le projet sont actifs.
            'unlockable' => $user?->isAdmin() === true && $this->isLocked() && $active,
            'unlocked_at' => $this->unlocked_at?->toIso8601String(),
            'unlock_requested_at' => $pendingRequest?->toIso8601String(),
            // L'auteur peut demander la réouverture d'une fiche verrouillée, une demande à la
            // fois ; une administratrice qui en est l'auteure la rouvre elle-même.
            'unlock_requestable' => $user !== null && $this->author_id === $user->id && ! $user->isAdmin()
                && $this->isLocked() && $active && $pendingRequest === null,
            'unlocked_by' => $this->unlockedBy ? ['id' => $this->unlockedBy->id, 'name' => $this->unlockedBy->name] : null,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name],
            // Section 1 : on la reprend de la séance, elle ne se modifie pas depuis la fiche.
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date->toDateString(),
                'start_time' => substr((string) $session->start_time, 0, 5),
                'end_time' => substr((string) $session->end_time, 0, 5),
                'session_type' => $session->session_type,
                'session_type_detail' => $session->session_type_detail,
                'project' => ['id' => $session->project->id, 'name' => $session->project->name],
                'location' => ['id' => $session->location->id, 'name' => $session->location->name, 'city' => $session->location->city],
                'sub_projects' => $session->subProjects
                    ->map(fn ($group) => ['id' => $group->id, 'name' => $group->name, 'attendees' => $group->pivot->getAttribute('attendees')])
                    ->values(),
            ],
            ...$this->resource->only(LogbookEntry::FIELDS),
            'evaluation' => $this->evaluation?->only(ParticipantEvaluation::FIELDS)
                ?? array_fill_keys(ParticipantEvaluation::FIELDS, null),
            'exercises' => $this->exercises->pluck('label')->values(),
        ];
    }
}
