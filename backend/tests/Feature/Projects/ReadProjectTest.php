<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
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

    public function test_la_liste_renvoie_tous_les_projets_avec_leur_statut(): void
    {
        $active = Project::factory()->create(['name' => 'BRAISES', 'start_date' => '2026-09-01']);
        Project::factory()->create(['name' => 'Projet pilote 2025', 'status' => 'archived', 'start_date' => '2025-01-01']);
        SubProject::factory()->count(4)->for($active)->create();

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Le plus récent d'abord.
            ->assertJsonPath('data.0.name', 'BRAISES')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.sub_projects_count', 4)
            ->assertJsonPath('data.1.status', 'archived')
            ->assertJsonMissingPath('data.0.sub_projects');
    }

    public function test_le_detail_renvoie_les_sous_projets_et_le_lieu(): void
    {
        $project = Project::factory()->create(['name' => 'Classes cousines']);
        SubProject::factory()->for($project)->create(['name' => 'CM1 Peyssonnel']);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Classes cousines')
            ->assertJsonPath('data.default_location.id', $project->default_location_id)
            ->assertJsonPath('data.sub_projects.0.name', 'CM1 Peyssonnel');
    }

    public function test_un_projet_inexistant_renvoie_404(): void
    {
        $this->getJson('/api/projects/999999')->assertNotFound();
    }
}
