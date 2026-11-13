<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'administratrice est aussi intervenante (#272, demande de la cliente du 13/11/2026) : on
 * peut la mettre dans une équipe, lui confier une séance, et elle remplit sa fiche. Le rôle
 * décide des droits ; l'équipe décide de qui intervient.
 */
class AdminAsContributorTest extends TestCase
{
    use RefreshDatabase;

    /** L'administratrice, qui anime aussi des séances. */
    private User $admin;

    /**
     * On prépare ce que tous les tests de la classe partagent : on est le 13 novembre 2026.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Mariette Travard']);
        Sanctum::actingAs($this->admin);
    }

    public function test_l_administratrice_cree_un_projet_dont_elle_est_la_principale(): void
    {
        $this->postJson('/api/projects', [
            'name' => 'Ateliers du regard',
            'default_location_id' => Location::factory()->create()->id,
            'principal_user_id' => $this->admin->id,
        ])->assertCreated()
            ->assertJsonPath('data.team.0.name', 'Mariette Travard')
            ->assertJsonPath('data.team.0.member_role', 'lead');
    }

    public function test_seule_sur_l_application_elle_va_du_projet_a_la_fiche(): void
    {
        $projectId = $this->postJson('/api/projects', [
            'name' => 'Ateliers du regard',
            'default_location_id' => Location::factory()->create()->id,
            'principal_user_id' => $this->admin->id,
        ])->assertCreated()->json('data.id');

        $sessionId = $this->postJson("/api/projects/{$projectId}/sessions", [
            'session_date' => '2026-11-12',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_type' => 'practice_workshop',
            'location_id' => Location::factory()->create()->id,
            'referent_id' => $this->admin->id,
            'sub_project_ids' => SubProject::query()->where('project_id', $projectId)->pluck('id')->all(),
        ])->assertCreated()->json('data.id');

        // Le tableau de bord lui propose sa fiche, comme à n'importe quel·le intervenant·e.
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonCount(1, 'to_fill')
            ->assertJsonPath('to_fill.0.id', $sessionId);

        $this->postJson("/api/sessions/{$sessionId}/logbook-entry", [])->assertCreated();
        $this->assertDatabaseHas('logbook_entries', ['scheduled_session_id' => $sessionId, 'author_id' => $this->admin->id]);
    }

    public function test_une_administratrice_ou_une_super_utilisatrice_rejoint_une_equipe_comme_ponctuelle(): void
    {
        $project = Project::factory()->create();
        SubProject::factory()->for($project)->create();
        $lead = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        $project->members()->attach($lead->id, ['member_role' => 'lead']);
        $superUser = User::factory()->role(UserRole::SuperUser)->create(['name' => 'Emilie Garetier']);

        foreach ([$this->admin, $superUser] as $user) {
            $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $user->id])->assertOk();
            $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $user->id, 'member_role' => 'occasional']);
        }
    }

    public function test_on_peut_la_nommer_principale_d_un_projet_existant(): void
    {
        $project = Project::factory()->create();
        $lead = User::factory()->role(UserRole::Contributor)->create();
        $project->members()->attach($lead->id, ['member_role' => 'lead']);

        $this->patchJson("/api/projects/{$project->id}", ['principal_user_id' => $this->admin->id])->assertOk();

        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $this->admin->id, 'member_role' => 'lead']);
    }

    public function test_le_menu_des_equipes_propose_tous_les_comptes_actifs_et_rien_que_leur_nom(): void
    {
        $lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        $emilie = User::factory()->role(UserRole::SuperUser)->create(['name' => 'Emilie Garetier']);
        User::factory()->role(UserRole::Contributor)->create(['name' => 'Ancien Intervenant', 'is_active' => false]);

        $this->getJson('/api/users?equipe=1')->assertOk()->assertExactJson(['data' => [
            ['id' => $emilie->id, 'name' => 'Emilie Garetier'],
            ['id' => $lea->id, 'name' => 'Léa Fournier'],
            ['id' => $this->admin->id, 'name' => 'Mariette Travard'],
        ]]);
    }

    public function test_le_filtre_des_equipes_ne_s_ouvre_pas_par_une_valeur_quelconque(): void
    {
        $this->getJson('/api/users?equipe=non')->assertUnprocessable()->assertJsonValidationErrors('equipe');
    }
}
