<?php

namespace Tests\Feature\Lists;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Financeurs gérés par l'administratrice (#249) : création, renommage, désactivation, jamais de suppression. */
class FunderManagementTest extends TestCase
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

    public function test_la_liste_montre_les_financeurs_actifs_d_abord_avec_leur_usage(): void
    {
        $drac = Funder::query()->create(['name' => 'DRAC', 'type' => 'state']);
        Funder::query()->create(['name' => 'Ancien mécène', 'type' => 'foundation', 'is_active' => false]);
        Project::factory()->create()->funders()->attach($drac->id);

        $this->getJson('/api/funders')->assertOk()
            ->assertJsonPath('data.0.name', 'DRAC')
            ->assertJsonPath('data.0.projects_count', 1)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.is_active', false);
    }

    public function test_cree_un_financeur(): void
    {
        $this->postJson('/api/funders', ['name' => '  Fondation   de France ', 'type' => 'foundation'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Fondation de France')
            ->assertJsonPath('data.type', 'foundation')
            ->assertJsonPath('data.is_active', true);
        $this->postJson('/api/funders', ['name' => 'Mairie'])->assertCreated()->assertJsonPath('data.type', 'other');
    }

    public function test_refuse_un_doublon_un_nom_vide_et_une_categorie_inconnue(): void
    {
        Funder::query()->create(['name' => 'Région Sud', 'type' => 'local_authority']);

        $this->postJson('/api/funders', ['name' => 'région sud'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ce financeur existe déjà.');
        $this->postJson('/api/funders', ['name' => ''])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Indiquez le nom du financeur.');
        $this->postJson('/api/funders', ['name' => 'X', 'type' => 'banque'])->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_renomme_et_desactive_sans_toucher_aux_projets(): void
    {
        $funder = Funder::query()->create(['name' => 'DRAC PACA', 'type' => 'state']);
        $project = Project::factory()->create();
        $project->funders()->attach($funder->id);

        $this->patchJson("/api/funders/{$funder->id}", ['name' => 'DRAC Provence-Alpes-Côte d’Azur'])->assertOk()
            ->assertJsonPath('data.name', 'DRAC Provence-Alpes-Côte d’Azur');
        $this->patchJson("/api/funders/{$funder->id}", ['name' => 'drac provence-alpes-côte d’azur'])->assertOk();
        $this->patchJson("/api/funders/{$funder->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame([$funder->id], $project->funders()->pluck('funders.id')->all());
    }

    public function test_un_financeur_desactive_n_est_plus_propose_pour_un_nouveau_projet_mais_reste_sur_les_siens(): void
    {
        $old = Funder::query()->create(['name' => 'Ancien', 'type' => 'other', 'is_active' => false]);
        $location = Location::factory()->create();
        $lead = User::factory()->role(UserRole::Contributor)->create();

        $this->postJson('/api/projects', ['name' => 'P', 'default_location_id' => $location->id, 'principal_user_id' => $lead->id, 'funder_ids' => [$old->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn ($errors) => str_contains(json_encode($errors, JSON_UNESCAPED_UNICODE), 'n’est plus actif'));

        $project = Project::factory()->create();
        $project->funders()->attach($old->id);
        $this->patchJson("/api/projects/{$project->id}", ['funder_ids' => [$old->id]])->assertOk();
    }

    public function test_reserve_a_l_administratrice(): void
    {
        $funder = Funder::query()->create(['name' => 'DRAC', 'type' => 'state']);
        foreach ([UserRole::SuperUser, UserRole::Contributor] as $role) {
            Sanctum::actingAs(User::factory()->role($role)->create());
            $this->postJson('/api/funders', ['name' => 'X'])->assertForbidden();
            $this->patchJson("/api/funders/{$funder->id}", ['name' => 'Y'])->assertForbidden();
        }
        $this->patchJson('/api/funders/abc', ['name' => 'Y'])->assertNotFound();
    }
}
