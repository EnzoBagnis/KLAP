<?php

namespace Tests\Feature\Sessions;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;

trait SessionTestHelpers
{
    /** Projet avec deux groupes et une intervenante principale affectée. */
    protected function projectWithTeam(array $attributes = []): array
    {
        $project = Project::factory()->create($attributes);
        $groups = SubProject::factory()->count(2)->for($project)->create();
        $lead = User::factory()->role(UserRole::Contributor)->create();
        $project->members()->attach($lead->id, ['member_role' => 'lead']);

        return [$project, $groups, $lead];
    }

    /** Séance d'un projet, rattachée aux groupes donnés (un seul par défaut). */
    protected function sessionFor(Project $project, array $attributes = [], $groups = null): ScheduledSession
    {
        $session = ScheduledSession::factory()->create(['project_id' => $project->id] + $attributes);
        $groups ??= [$project->subProjects()->first()];
        foreach ($groups as $group) {
            $session->subProjects()->attach($group->id, ['project_id' => $project->id]);
        }

        return $session;
    }
}
