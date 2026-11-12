<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lecture des fiches (#19, #141) : qui lit quoi, et ce que la réponse dit des droits.
 */
class ReadLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
    }

    /**
     * Les cas de « l administration lit toutes les fiches ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function readers(): array
    {
        return ['administratrice' => [UserRole::Admin], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('readers')]
    public function test_l_administration_lit_toutes_les_fiches(UserRole $role): void
    {
        $id = $this->createEntry(['objectives' => 'Objectif']);

        $this->actingAs(User::factory()->role($role)->create())
            ->getJson("/api/logbook-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Objectif')
            ->assertJsonPath('data.editable', true); // droits emboîtés (#141)
    }

    public function test_un_intervenant_lit_les_fiches_des_projets_ou_il_intervient(): void
    {
        $id = $this->createEntry(['objectives' => 'Objectif']);
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->actingAs($colleague)->getJson("/api/logbook-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Objectif')
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.unlock_requestable', false);
        $this->actingAs($colleague)->getJson('/api/logbook-entries')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($colleague)->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertOk();
    }

    public function test_un_intervenant_ne_lit_que_ses_fiches_et_reprend_ses_brouillons(): void
    {
        $id = $this->createEntry();
        config(['app.debug' => false]);

        $this->getJson("/api/logbook-entries/{$id}")->assertOk()->assertJsonPath('data.author.id', $this->referent->id);
        $this->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/logbook-entries?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/logbook-entries?status=submitted')->assertOk()->assertJsonCount(0, 'data');

        $other = User::factory()->role(UserRole::Contributor)->create();
        $hidden = $this->actingAs($other)->getJson("/api/logbook-entries/{$id}")->assertNotFound()->getContent();
        $this->assertSame($hidden, $this->actingAs($other)->getJson('/api/logbook-entries/999999')->assertNotFound()->getContent());
        $this->actingAs($other)->getJson('/api/logbook-entries')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertNotFound();
    }

    public function test_un_statut_inconnu_est_refuse_et_un_visiteur_aussi(): void
    {
        $this->actingAs($this->referent)->getJson('/api/logbook-entries?status=perdu')
            ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Le statut demandé n’existe pas.');
    }

    public function test_un_membre_retire_du_projet_ne_lit_plus_que_ses_fiches(): void
    {
        $id = $this->createEntry();
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);
        $this->actingAs($colleague)->getJson("/api/logbook-entries/{$id}")->assertOk();

        $this->project->members()->detach($colleague->id);
        $this->actingAs($colleague)->getJson("/api/logbook-entries/{$id}")->assertNotFound();
        $this->actingAs($colleague)->getJson("/api/sessions/{$this->session->id}/logbook-entry")->assertNotFound();
    }

    public function test_la_liste_d_un_collegue_ne_montre_pas_les_autres_projets(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        // Fiche soumise d'un autre projet, où le collègue n'intervient pas.
        $otherSession = ScheduledSession::factory()->create(['project_id' => Project::factory()->create()->id, 'session_date' => '2026-10-05']);
        DB::table('logbook_entries')->insert(['scheduled_session_id' => $otherSession->id, 'author_id' => $otherSession->referent_id,
            'status' => 'submitted', 'adjustment_done' => false, 'keep_in_class' => false, 'edit_deadline' => '2026-10-15',
            'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->actingAs($colleague)->getJson('/api/logbook-entries?status=submitted')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($colleague)->getJson('/api/logbook-entries')->assertJsonCount(1, 'data');
    }

    public function test_les_seances_indiquent_leur_fiche(): void
    {
        $id = $this->createEntry();

        $this->getJson('/api/sessions?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.0.logbook_entry.id', $id)
            ->assertJsonPath('data.0.logbook_entry.status', 'draft');
    }
}
