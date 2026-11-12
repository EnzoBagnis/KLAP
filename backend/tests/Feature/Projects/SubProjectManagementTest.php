<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Groupes d'un projet après sa création (#250) : ajouter, corriger, retirer, remettre. */
class SubProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Le projet des tests. */
    private Project $project;

    /** Le groupe des tests. */
    private SubProject $group;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->project = Project::factory()->create();
        $this->group = SubProject::factory()->for($this->project)->create(['name' => 'Seconde 3']);
    }

    public function test_ajoute_un_groupe_avec_son_age_et_son_effectif(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/sub-projects", [
            'name' => '  Seconde   7 ', 'age_range' => '15-16 ans', 'expected_headcount' => 28, 'postal_code' => '13003', 'city' => 'Marseille',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Seconde 7')
            ->assertJsonPath('data.age_range', '15-16 ans')
            ->assertJsonPath('data.expected_headcount', 28)
            ->assertJsonPath('data.is_active', true);

        $this->getJson("/api/projects/{$this->project->id}")->assertJsonCount(2, 'data.sub_projects');
    }

    public function test_refuse_un_nom_deja_pris_dans_le_projet_et_des_valeurs_invalides(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/sub-projects", ['name' => 'seconde 3'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ce projet a déjà un groupe de ce nom.');
        $this->postJson("/api/projects/{$this->project->id}/sub-projects", ['name' => ''])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Indiquez le nom du groupe.');
        $this->postJson("/api/projects/{$this->project->id}/sub-projects", ['name' => 'X', 'expected_headcount' => 0, 'postal_code' => '1300'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.expected_headcount.0', 'Le champ effectif prévu doit valoir au moins 1.')
            ->assertJsonPath('errors.postal_code.0', 'Le code postal doit compter 5 chiffres.');
        // Le même nom dans un autre projet est libre.
        $other = Project::factory()->create();
        $this->postJson("/api/projects/{$other->id}/sub-projects", ['name' => 'Seconde 3'])->assertCreated();
    }

    public function test_corrige_un_groupe_sans_toucher_aux_autres_champs(): void
    {
        $this->group->update(['age_range' => '15-16 ans', 'expected_headcount' => 20]);

        $this->patchJson("/api/sub-projects/{$this->group->id}", ['expected_headcount' => 24])->assertOk()
            ->assertJsonPath('data.expected_headcount', 24)
            ->assertJsonPath('data.age_range', '15-16 ans');
        $this->patchJson("/api/sub-projects/{$this->group->id}", ['name' => 'SECONDE 3'])->assertOk()->assertJsonPath('data.name', 'SECONDE 3');
        $this->patchJson("/api/sub-projects/{$this->group->id}", ['age_range' => ''])->assertOk()->assertJsonPath('data.age_range', null);
    }

    public function test_retire_puis_remet_un_groupe_mais_garde_toujours_un_groupe_actif(): void
    {
        foreach ([false, 0, '0'] as $off) {
            $this->patchJson("/api/sub-projects/{$this->group->id}", ['is_active' => $off])->assertUnprocessable()
                ->assertJsonPath('errors.is_active.0', 'Un projet garde au moins un groupe actif : ajoutez-en un autre avant de retirer celui-ci.');
        }

        SubProject::factory()->for($this->project)->create(['name' => 'Seconde 7']);
        $this->patchJson("/api/sub-projects/{$this->group->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson("/api/sub-projects/{$this->group->id}", ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertSame(2, $this->project->subProjects()->count());
    }

    public function test_un_projet_archive_ne_bouge_plus(): void
    {
        $this->project->forceFill(['status' => 'archived'])->save();

        $this->postJson("/api/projects/{$this->project->id}/sub-projects", ['name' => 'Seconde 7'])->assertStatus(409)
            ->assertJsonPath('message', 'Ce projet est archivé : restaurez-le avant de modifier ses groupes.');
        $this->patchJson("/api/sub-projects/{$this->group->id}", ['expected_headcount' => 3])->assertStatus(409);
    }

    public function test_reserve_a_l_administratrice(): void
    {
        foreach ([UserRole::SuperUser, UserRole::Contributor] as $role) {
            Sanctum::actingAs(User::factory()->role($role)->create());
            $this->postJson("/api/projects/{$this->project->id}/sub-projects", ['name' => 'X'])->assertForbidden();
            $this->patchJson("/api/sub-projects/{$this->group->id}", ['name' => 'Y'])->assertForbidden();
        }
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->postJson('/api/projects/9999/sub-projects', ['name' => 'X'])->assertNotFound();
        $this->patchJson('/api/sub-projects/9999', ['name' => 'X'])->assertNotFound();
        $this->patchJson('/api/sub-projects/1x', ['name' => 'X'])->assertNotFound();
    }
}
