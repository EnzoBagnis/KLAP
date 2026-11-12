<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;

trait LogbookTestHelpers
{
    /** Le projet des tests. */
    protected Project $project;

    /** La séance des tests. */
    protected ScheduledSession $session;

    /** L'intervenant·e référent·e de la séance. */
    protected User $referent;

    /**
     * On prépare le décor commun : on est le 6 octobre 2026 à 9 h, et la séance du 5 octobre
     * couvre deux groupes, avec son intervenant·e référent·e.
     *
     * @param  array<string, mixed>  $sessionAttributes  ce qu'on change sur la séance
     */
    protected function setUpSession(array $sessionAttributes = []): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(9, 0));
        $this->project = Project::factory()->create();
        $groups = SubProject::factory()->count(2)->for($this->project)->create();
        $this->referent = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($this->referent->id, ['member_role' => 'lead']);
        $this->session = ScheduledSession::factory()->create([
            'project_id' => $this->project->id,
            'referent_id' => $this->referent->id,
            'session_date' => '2026-10-05',
        ] + $sessionAttributes);
        foreach ($groups as $group) {
            $this->session->subProjects()->attach($group->id, ['project_id' => $this->project->id]);
        }
    }

    /**
     * Les identifiants des groupes de la séance.
     *
     * @return list<int>
     */
    protected function groupIds(): array
    {
        return $this->session->subProjects()->pluck('sub_projects.id')->all();
    }

    /**
     * Une fiche complète, prête à être soumise.
     *
     * @param  array<string, mixed>  $overrides  ce qu'on change dans la fiche
     * @return array<string, mixed>
     */
    protected function completePayload(array $overrides = []): array
    {
        // Les listes (exercices, présents) sont remplacées en entier, pas fusionnées indice par indice.
        $lists = array_intersect_key($overrides, array_flip(['exercises', 'attendees']));
        $overrides = array_diff_key($overrides, $lists);

        return $lists + array_replace_recursive([
            'objectives' => 'Explorer le poids du corps et l’écoute du groupe.',
            'highlights' => 'Un duo improvisé très juste en fin de séance.',
            'adjustment_done' => false,
            'next_steps' => 'Reprendre le travail au sol.',
            'evaluation' => [
                'engagement' => 4, 'expression' => 3, 'autonomy' => 3,
                'interaction' => 'écoute, entraide', 'feeling' => 'enthusiasm', 'progress' => 'improvement',
            ],
            'exercises' => ['Échauffement en cercle', 'Marche et arrêts'],
            'attendees' => array_map(fn ($id) => ['sub_project_id' => $id, 'count' => 12], $this->groupIds()),
        ], $overrides);
    }

    /**
     * L'intervenant·e référent·e ouvre la fiche de la séance.
     *
     * @param  array<string, mixed>  $payload  la première saisie
     * @return int l'identifiant de la fiche
     */
    protected function createEntry(array $payload = []): int
    {
        return $this->actingAs($this->referent)
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry", $payload)
            ->assertCreated()
            ->json('data.id');
    }
}
