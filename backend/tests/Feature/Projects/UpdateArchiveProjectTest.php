<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\Location;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Logbook\LogbookTestHelpers;
use Tests\TestCase;

/** Modifier, archiver et restaurer un projet (#152). */
class UpdateArchiveProjectTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /** L'administratrice. */
    private User $admin;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->project->update(['name' => 'Arts Danse au Lycée Charles']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    /**
     * On modifie le projet, par défaut en tant qu'administratrice.
     *
     * @param  array<string, mixed>  $data
     * @return TestResponse<JsonResponse>
     */
    private function edit(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->patchJson("/api/projects/{$this->project->id}", $data);
    }

    /**
     * On archive le projet en saisissant un nom de confirmation.
     *
     * @return TestResponse<JsonResponse>
     */
    private function archive(string $name, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->postJson("/api/projects/{$this->project->id}/archive", ['confirm_name' => $name]);
    }

    public function test_modifie_seulement_les_champs_envoyes(): void
    {
        $before = $this->project->fresh();

        $this->edit(['name' => 'Arts Danse 2026-2027', 'planned_sessions' => 40, 'status' => 'finished'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Arts Danse 2026-2027')
            ->assertJsonPath('data.planned_sessions', 40)
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.start_date', $before->start_date->toDateString())
            ->assertJsonPath('data.default_location.id', $before->default_location_id);
    }

    public function test_les_dates_sont_comparees_aux_valeurs_enregistrees(): void
    {
        // Projet du 15/09/2026 au 19/06/2027 (fabrique).
        $this->edit(['end_date' => '2026-09-01'])->assertUnprocessable()
            ->assertJsonPath('errors.end_date.0', 'La date de fin doit être postérieure ou égale à la date de début.');
        $this->edit(['start_date' => '2027-07-01'])->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'La date de fin doit être postérieure ou égale à la date de début.');
        $this->edit(['start_date' => '2026-09-01', 'end_date' => '2026-12-20'])->assertOk();
        $this->edit(['end_date' => null])->assertOk()->assertJsonPath('data.end_date', null);
    }

    public function test_reprend_les_regles_de_creation(): void
    {
        $this->edit(['name' => ''])->assertJsonValidationErrors('name');
        $this->edit(['planned_sessions' => 0])->assertJsonValidationErrors('planned_sessions');
        $this->edit(['status' => 'archived'])->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Ce statut n’est pas possible ici : un projet s’archive avec la confirmation de son nom.');
        $this->edit(['default_location_id' => Location::factory()->create(['is_active' => false])->id])->assertJsonValidationErrors('default_location_id');
        $this->edit(['project_type_id' => ProjectType::factory()->create(['is_active' => false])->id])->assertJsonValidationErrors('project_type_id');
        $this->edit(['principal_user_id' => User::factory()->create(['is_active' => false])->id])->assertJsonValidationErrors('principal_user_id');
        $this->edit(['funder_ids' => [999]])->assertJsonValidationErrors('funder_ids.0');
    }

    public function test_un_lieu_ou_un_type_devenu_inactif_reste_accepte_s_il_ne_change_pas(): void
    {
        $project = $this->project->fresh();
        Location::query()->whereKey($project->default_location_id)->update(['is_active' => false]);
        ProjectType::query()->whereKey($project->project_type_id)->update(['is_active' => false]);

        $this->edit(['default_location_id' => $project->default_location_id, 'project_type_id' => $project->project_type_id, 'name' => 'Renommé'])
            ->assertOk();
    }

    public function test_changer_d_intervenant_principal_garde_l_ancien_en_ponctuel(): void
    {
        $id = $this->createEntry();
        $newLead = User::factory()->role(UserRole::Contributor)->create();

        $this->edit(['principal_user_id' => $newLead->id])->assertOk();

        $roles = DB::table('project_user')->where('project_id', $this->project->id)->pluck('member_role', 'user_id');
        $this->assertSame('lead', $roles[$newLead->id]);
        $this->assertSame('occasional', $roles[$this->referent->id]);
        // L'ancien·ne principal·e lit toujours ses fiches.
        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")->assertOk();
    }

    public function test_reprendre_le_meme_principal_ne_change_rien(): void
    {
        $this->edit(['principal_user_id' => $this->referent->id])->assertOk();

        $this->assertSame(['lead'], DB::table('project_user')->where('project_id', $this->project->id)->pluck('member_role')->all());
    }

    public function test_les_financeurs_envoyes_remplacent_la_liste(): void
    {
        $drac = Funder::query()->create(['name' => 'DRAC', 'type' => 'state']);
        $ville = Funder::query()->create(['name' => 'Ville de Marseille', 'type' => 'local_authority']);
        $this->project->funders()->attach($drac->id);

        $this->edit(['funder_ids' => [$ville->id]])->assertOk()->assertJsonCount(1, 'data.funders')->assertJsonPath('data.funders.0.id', $ville->id);
        $this->edit(['name' => 'Sans toucher aux financeurs'])->assertOk()->assertJsonCount(1, 'data.funders');
        $this->edit(['funder_ids' => []])->assertOk()->assertJsonCount(0, 'data.funders');
    }

    public function test_seule_l_administratrice_modifie_archive_et_restaure(): void
    {
        foreach ([User::factory()->role(UserRole::SuperUser)->create(), $this->referent] as $user) {
            $this->edit(['name' => 'X'], $user)->assertForbidden();
            $this->archive('Arts Danse au Lycée Charles', $user)->assertForbidden();
            $this->actingAs($user)->postJson("/api/projects/{$this->project->id}/restore")->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->patchJson("/api/projects/{$this->project->id}", ['name' => 'X'])->assertUnauthorized();
    }

    public function test_projet_inexistant(): void
    {
        $this->actingAs($this->admin)->patchJson('/api/projects/9999', ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->postJson('/api/projects/abc/archive', ['confirm_name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->postJson('/api/projects/9999/restore')->assertNotFound();
    }

    public function test_l_archivage_demande_le_nom_exact(): void
    {
        $this->archive('')->assertJsonPath('errors.confirm_name.0', 'Saisissez le nom du projet pour confirmer son archivage.');
        $this->archive('Arts Danse')->assertJsonPath('errors.confirm_name.0', 'Le nom saisi ne correspond pas au nom du projet.');
        $this->archive('arts danse au lycée charles')
            ->assertJsonPath('errors.confirm_name.0', 'Attention aux majuscules : saisissez exactement « Arts Danse au Lycée Charles ».');
        $this->assertSame('active', $this->project->fresh()->status);

        // On ignore les espaces en trop.
        $this->archive('  Arts  Danse au Lycée Charles ')->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.archived_at', now()->toIso8601String());
    }

    public function test_l_archivage_ne_supprime_rien_et_bloque_la_modification(): void
    {
        $id = $this->createEntry();

        $this->archive('Arts Danse au Lycée Charles')->assertOk();

        $this->assertDatabaseHas('scheduled_sessions', ['id' => $this->session->id]);
        $this->assertDatabaseHas('logbook_entries', ['id' => $id]);
        $this->assertDatabaseHas('sub_projects', ['project_id' => $this->project->id]);
        $this->edit(['name' => 'X'])->assertStatus(409);
        $this->archive('Arts Danse au Lycée Charles')->assertStatus(409);
        // On le masque de la liste par défaut, mais il reste lisible en détail.
        $this->actingAs($this->admin)->getJson('/api/projects')->assertJsonCount(0, 'data');
        $this->actingAs($this->admin)->getJson("/api/projects/{$this->project->id}")->assertOk();
    }

    public function test_restaurer_un_projet(): void
    {
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/restore")->assertStatus(409);
        $this->archive('Arts Danse au Lycée Charles')->assertOk();

        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/restore", ['status' => 'archived'])
            ->assertJsonValidationErrors('status');
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/restore", ['status' => 'finished'])
            ->assertOk()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.archived_at', null);
        $this->edit(['name' => 'De nouveau modifiable'])->assertOk();
    }

    public function test_un_principal_desactive_reste_accepte_s_il_ne_change_pas(): void
    {
        $this->referent->update(['is_active' => false]);

        $this->edit(['principal_user_id' => $this->referent->id, 'name' => 'Renommé'])->assertOk();
        $other = User::factory()->role(UserRole::Contributor)->create(['is_active' => false]);
        $this->edit(['principal_user_id' => $other->id])->assertJsonValidationErrors('principal_user_id');
    }

    public function test_un_ponctuel_promu_principal_garde_une_seule_ligne_sans_dates(): void
    {
        $occasional = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($occasional->id, ['member_role' => 'occasional', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

        $this->edit(['principal_user_id' => $occasional->id])->assertOk();

        $row = DB::table('project_user')->where('project_id', $this->project->id)->where('user_id', $occasional->id)->sole();
        $this->assertSame('lead', $row->member_role);
        $this->assertNull($row->start_date);
        $this->assertNull($row->end_date);
        $this->assertSame(2, DB::table('project_user')->where('project_id', $this->project->id)->count());
    }

    public function test_les_champs_de_sous_projet_sont_refuses(): void
    {
        $this->edit(['sub_projects' => [['name' => 'Seconde 3']]])->assertUnprocessable()
            ->assertJsonPath('errors.sub_projects.0', 'Les sous-projets, leur structure, leur ville, leur code postal, leur âge et leur effectif ne se modifient pas ici.');
        $this->edit(['city' => 'Marseille'])->assertJsonValidationErrors('city');
        $this->edit(['age_range' => '15-16 ans', 'expected_headcount' => 24])->assertJsonValidationErrors(['age_range', 'expected_headcount']);
    }

    public function test_saisies_invalides_sans_erreur_serveur(): void
    {
        $this->edit(['start_date' => '2027-01-10', 'end_date' => '2027-01-01'])->assertJsonValidationErrors('end_date');
        $this->edit(['end_date' => 'pas-une-date'])->assertJsonValidationErrors('end_date');
        $this->archive('')->assertUnprocessable();
        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/archive", ['confirm_name' => ['x']])
            ->assertJsonValidationErrors('confirm_name');
        $this->edit(['project_type_id' => null])->assertOk()->assertJsonPath('data.project_type', null);
    }

    public function test_renommer_un_projet_non_decoupe_renomme_son_sous_projet(): void
    {
        $this->project->subProjects()->where('id', '<>', $this->groupIds()[0])->get()->each(function ($group) {
            DB::table('scheduled_session_sub_project')->where('sub_project_id', $group->id)->delete();
            $group->delete();
        });
        $this->project->subProjects()->update(['name' => 'Arts Danse au Lycée Charles']);

        $this->edit(['name' => 'Arts Danse 2026'])->assertOk()->assertJsonPath('data.sub_projects.0.name', 'Arts Danse 2026');
    }

    public function test_un_projet_decoupe_garde_les_noms_de_ses_groupes(): void
    {
        $names = $this->project->subProjects()->orderBy('id')->pluck('name')->all();

        $this->edit(['name' => 'Arts Danse 2026'])->assertOk();

        $this->assertSame($names, $this->project->subProjects()->orderBy('id')->pluck('name')->all());
    }

    public function test_un_projet_archive_gele_ses_seances_et_ses_fiches_jusqu_a_la_restauration(): void
    {
        $id = $this->createEntry();
        $this->archive('Arts Danse au Lycée Charles')->assertOk();

        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", $this->completePayload())->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/sessions?from=2026-10-01&to=2026-10-31')
            ->assertJsonPath('data.0.project.status', 'archived');

        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/restore")->assertOk();
        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", $this->completePayload())->assertOk();
    }

    public function test_restaurer_demande_une_connexion(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/restore")->assertUnauthorized();
    }

    public function test_restaurer_sans_statut_le_remet_actif(): void
    {
        $this->archive('Arts Danse au Lycée Charles')->assertOk();

        $this->actingAs($this->admin)->postJson("/api/projects/{$this->project->id}/restore")
            ->assertOk()->assertJsonPath('data.status', 'active');
    }
}
