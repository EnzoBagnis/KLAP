<?php

namespace Tests\Feature\Sessions;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait SessionTestHelpers
{
    /**
     * Un projet avec deux groupes et une intervenante principale affectée.
     *
     * @param  array<string, mixed>  $attributes  ce qu'on change sur le projet
     * @return array{0: Project, 1: Collection<int, SubProject>, 2: User}
     */
    protected function projectWithTeam(array $attributes = []): array
    {
        $project = Project::factory()->create($attributes);
        $groups = SubProject::factory()->count(2)->for($project)->create();
        $lead = User::factory()->role(UserRole::Contributor)->create();
        $project->members()->attach($lead->id, ['member_role' => 'lead']);

        return [$project, $groups, $lead];
    }

    /**
     * Une séance d'un projet, rattachée aux groupes donnés (un seul par défaut).
     *
     * @param  array<string, mixed>  $attributes
     * @param  iterable<SubProject>|null  $groups
     */
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
