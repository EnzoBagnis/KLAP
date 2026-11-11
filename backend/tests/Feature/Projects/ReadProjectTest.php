<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
    }

    public function test_la_liste_masque_les_projets_archives(): void
    {
        $active = Project::factory()->create(['name' => 'BRAISES', 'start_date' => '2026-09-01']);
        Project::factory()->create(['name' => 'Classes cousines', 'status' => 'draft', 'start_date' => '2026-10-01']);
        Project::factory()->create(['name' => 'Projet pilote 2025', 'status' => 'archived', 'start_date' => '2025-01-01']);
        SubProject::factory()->count(4)->for($active)->create();

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Le plus récent d'abord.
            ->assertJsonPath('data.0.name', 'Classes cousines')
            ->assertJsonPath('data.1.name', 'BRAISES')
            ->assertJsonPath('data.1.sub_projects_count', 4)
            ->assertJsonMissingPath('data.0.sub_projects');
    }

    public function test_la_liste_reaffiche_les_archives_sur_demande(): void
    {
        Project::factory()->create(['name' => 'BRAISES']);
        Project::factory()->create(['name' => 'Projet pilote 2025', 'status' => 'archived', 'start_date' => '2025-01-01']);

        $this->getJson('/api/projects?include_archived=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.status', 'archived');
    }

    public function test_le_detail_d_un_projet_archive_reste_accessible(): void
    {
        $archived = Project::factory()->create(['status' => 'archived']);

        $this->getJson("/api/projects/{$archived->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_le_detail_renvoie_les_sous_projets_et_le_lieu(): void
    {
        $project = Project::factory()->create(['name' => 'Classes cousines']);
        SubProject::factory()->for($project)->create(['name' => 'CM1 Peyssonnel']);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Classes cousines')
            ->assertJsonPath('data.default_location.id', $project->default_location_id)
            ->assertJsonPath('data.project_type.id', $project->project_type_id)
            ->assertJsonPath('data.sub_projects.0.name', 'CM1 Peyssonnel');
    }

    public function test_le_detail_renvoie_l_equipe_et_les_financeurs_mais_pas_la_liste(): void
    {
        $project = Project::factory()->create();
        // Le ponctuel est inséré avant et son nom précède : le principal doit quand même sortir en premier.
        $anna = User::factory()->role(UserRole::Contributor)->create(['name' => 'Anna Bellini']);
        $lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        $project->members()->attach($anna->id, ['member_role' => 'occasional']);
        $project->members()->attach($lea->id, ['member_role' => 'lead']);
        $project->funders()->attach(Funder::factory()->create(['name' => 'DRAC', 'type' => 'state'])->id);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.team')
            ->assertJsonPath('data.team.0.name', 'Léa Fournier')
            ->assertJsonPath('data.team.0.member_role', 'lead')
            ->assertJsonPath('data.team.1.member_role', 'occasional')
            ->assertJsonPath('data.funders.0', ['id' => $project->funders()->first()->id, 'name' => 'DRAC', 'type' => 'state']);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonMissingPath('data.0.team')
            ->assertJsonMissingPath('data.0.funders');
    }

    public function test_un_projet_inexistant_renvoie_404(): void
    {
        $this->getJson('/api/projects/999999')->assertNotFound();
    }
}
