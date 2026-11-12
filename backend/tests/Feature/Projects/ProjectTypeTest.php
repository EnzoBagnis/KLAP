<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Types de projet gérés par l'administratrice : création, renommage, désactivation. */
class ProjectTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
    }

    public function test_la_liste_montre_les_types_actifs_d_abord_avec_leur_usage(): void
    {
        $lycee = ProjectType::factory()->create(['name' => 'Partenariat lycée']);
        ProjectType::factory()->inactive()->create(['name' => 'Ancien type']);
        Project::factory()->count(2)->create(['project_type_id' => $lycee->id]);

        $this->getJson('/api/project-types')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Partenariat lycée')
            ->assertJsonPath('data.0.projects_count', 2)
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_cree_un_type(): void
    {
        $this->postJson('/api/project-types', ['name' => 'Résidence'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Résidence')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_refuse_un_doublon_et_un_nom_vide(): void
    {
        ProjectType::factory()->create(['name' => 'Résidence']);

        $this->postJson('/api/project-types', ['name' => 'Résidence'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ce type de projet existe déjà.');
        $this->postJson('/api/project-types', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_renomme_un_type_sans_toucher_aux_projets(): void
    {
        $type = ProjectType::factory()->create(['name' => 'Projet annuel ecole']);
        $project = Project::factory()->create(['project_type_id' => $type->id]);

        $this->patchJson("/api/project-types/{$type->id}", ['name' => 'Projet annuel école'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Projet annuel école');

        $this->assertSame($type->id, $project->fresh()->project_type_id);
    }

    public function test_garder_le_meme_nom_n_est_pas_un_doublon(): void
    {
        $type = ProjectType::factory()->create(['name' => 'Résidence']);

        $this->patchJson("/api/project-types/{$type->id}", ['name' => 'Résidence', 'is_active' => true])
            ->assertOk();
    }

    public function test_desactive_un_type_que_les_projets_gardent(): void
    {
        $type = ProjectType::factory()->create();
        $project = Project::factory()->create(['project_type_id' => $type->id]);

        $this->patchJson("/api/project-types/{$type->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame($type->id, $project->fresh()->project_type_id);
    }

    public function test_la_liste_ne_renvoie_que_les_champs_utiles(): void
    {
        ProjectType::factory()->create();

        $this->getJson('/api/project-types')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'is_active', 'projects_count']]])
            ->assertJsonMissingPath('data.0.created_at');
    }

    public function test_un_doublon_qui_differe_par_la_casse_est_refuse(): void
    {
        ProjectType::factory()->create(['name' => 'Résidence']);

        $this->postJson('/api/project-types', ['name' => 'résidence'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ce type de projet existe déjà.');
    }

    public function test_refuse_de_renommer_avec_le_nom_d_un_autre_type(): void
    {
        ProjectType::factory()->create(['name' => 'Résidence']);
        $other = ProjectType::factory()->create(['name' => 'Parcours collège']);

        $this->patchJson("/api/project-types/{$other->id}", ['name' => 'RÉSIDENCE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_refuse_un_nom_vide_ou_trop_long_en_modification(): void
    {
        $type = ProjectType::factory()->create();

        $this->patchJson("/api/project-types/{$type->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Le nom du type de projet est obligatoire.');
        $this->patchJson("/api/project-types/{$type->id}", ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Le nom du type de projet ne doit pas dépasser 100 caractères.');
    }

    public function test_refuse_un_nom_trop_long_en_creation(): void
    {
        $this->postJson('/api/project-types', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_refuse_un_etat_actif_non_booleen(): void
    {
        $type = ProjectType::factory()->create();

        $this->patchJson("/api/project-types/{$type->id}", ['is_active' => 'oui'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'L’état actif doit valoir vrai ou faux.');
    }

    public function test_un_type_inexistant_renvoie_404(): void
    {
        $this->patchJson('/api/project-types/999999', ['name' => 'Résidence'])->assertNotFound();
    }

    public function test_un_projet_garde_son_type_desactive_en_lecture(): void
    {
        $type = ProjectType::factory()->inactive()->create(['name' => 'Ancien type']);
        $project = Project::factory()->create(['project_type_id' => $type->id]);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.project_type.name', 'Ancien type');
    }

    public function test_aucune_route_de_suppression(): void
    {
        $type = ProjectType::factory()->create();

        $this->deleteJson("/api/project-types/{$type->id}")->assertStatus(405);
        $this->assertDatabaseHas('project_types', ['id' => $type->id]);
    }
}
