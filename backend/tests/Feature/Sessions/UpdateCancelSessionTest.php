<?php

namespace Tests\Feature\Sessions;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Logbook\LogbookTestHelpers;
use Tests\TestCase;

/** Modifier, annuler et rétablir une séance (#180). Aujourd'hui : 6 octobre 2026. */
class UpdateCancelSessionTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    private User $admin;

    private ScheduledSession $upcoming;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->upcoming = ScheduledSession::factory()->create([
            'project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-10-12',
        ]);
        $this->upcoming->subProjects()->attach($this->groupIds()[0], ['project_id' => $this->project->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'session_date' => '2026-10-13',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'viewing_workshop',
            'location_id' => $this->upcoming->location_id,
            'referent_id' => $this->referent->id,
            'sub_project_ids' => $this->groupIds(),
        ], $overrides);
    }

    private function edit(array $data, ?ScheduledSession $session = null, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->patchJson('/api/sessions/'.($session ?? $this->upcoming)->id, $data);
    }

    public function test_modifie_une_seance_sans_fiche(): void
    {
        $this->edit($this->payload())
            ->assertOk()
            ->assertJsonPath('data.session_date', '2026-10-13')
            ->assertJsonPath('data.start_time', '14:00')
            ->assertJsonPath('data.session_type', 'viewing_workshop')
            ->assertJsonCount(2, 'data.sub_projects');
    }

    public function test_reprend_les_regles_de_la_programmation(): void
    {
        $this->edit($this->payload(['end_time' => '13:00']))->assertJsonPath('errors.end_time.0', 'La fin de la séance doit être après son début.');
        $this->edit($this->payload(['sub_project_ids' => []]))->assertJsonValidationErrors('sub_project_ids');
        $this->edit($this->payload(['referent_id' => User::factory()->role(UserRole::Contributor)->create()->id]))
            ->assertJsonValidationErrors('referent_id');
        // Chevauchement avec la séance du 5 octobre de 10 h à 12 h ; la séance elle-même ne compte pas.
        $this->edit($this->payload(['session_date' => '2026-10-05', 'start_time' => '11:00', 'end_time' => '13:00']))
            ->assertJsonValidationErrors('start_time');
        $this->edit($this->payload(['session_date' => '2026-10-12', 'start_time' => '10:30', 'end_time' => '12:30']))->assertOk();
    }

    public function test_un_lieu_desactive_reste_accepte_s_il_ne_change_pas(): void
    {
        Location::query()->whereKey($this->upcoming->location_id)->update(['is_active' => false]);

        $this->edit($this->payload())->assertOk();
        $this->edit($this->payload(['location_id' => Location::factory()->create(['is_active' => false])->id]))
            ->assertJsonValidationErrors('location_id');
    }

    public function test_une_seance_qui_a_une_fiche_ne_se_modifie_ni_ne_s_annule(): void
    {
        $this->createEntry();

        $this->edit($this->payload(), $this->session)->assertStatus(409)
            ->assertJsonPath('message', 'Cette séance a déjà une fiche : elle ne se modifie plus.');
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->session->id}/cancel")->assertStatus(409);
    }

    public function test_annuler_puis_retablir(): void
    {
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/cancel")->assertStatus(409);
        $this->edit($this->payload())->assertStatus(409);
        // Annulée : pas de fiche possible, et le créneau est libre pour une autre séance.
        $this->assertDatabaseHas('scheduled_sessions', ['id' => $this->upcoming->id, 'status' => 'cancelled']);

        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/restore")
            ->assertOk()->assertJsonPath('data.status', 'planned');
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/restore")->assertStatus(409);
    }

    public function test_ne_retablit_pas_une_seance_dont_le_creneau_est_pris(): void
    {
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/cancel")->assertOk();
        $other = ScheduledSession::factory()->create([
            'project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-10-12',
        ]);

        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/restore")
            ->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->assertSame('cancelled', $this->upcoming->fresh()->status);
        $this->assertSame('planned', $other->fresh()->status);
    }

    public function test_une_seance_dont_le_delai_est_passe_ne_se_modifie_plus(): void
    {
        // Séance du 5 octobre sans fiche ; le 16 octobre, son délai de J+10 est passé.
        $this->travelTo(now()->setDate(2026, 10, 16));

        $this->edit($this->payload(['session_date' => '2026-10-16']), $this->session)->assertStatus(409)
            ->assertJsonPath('message', 'Le délai de 10 jours après cette séance est passé : elle ne se modifie plus.');
        // Elle peut encore être annulée : elle n'a pas eu lieu.
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->session->id}/cancel")->assertOk();
    }

    public function test_un_groupe_desactive_reste_accepte_s_il_est_deja_lie(): void
    {
        $linked = $this->groupIds()[0];
        SubProject::query()->whereKey($linked)->update(['is_active' => false]);

        $this->edit($this->payload(['sub_project_ids' => [$linked]]))->assertOk();
        $other = SubProject::factory()->for($this->project)->create(['is_active' => false]);
        $this->edit($this->payload(['sub_project_ids' => [$other->id]]))->assertJsonValidationErrors('sub_project_ids.0');
    }

    public function test_ne_retablit_pas_si_l_intervenant_n_est_plus_actif(): void
    {
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/cancel")->assertOk();
        $this->referent->update(['is_active' => false]);

        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/restore")
            ->assertUnprocessable()
            ->assertJsonPath('errors.session.0', 'L’intervenant·e de cette séance n’est plus actif·ve ou plus affecté·e au projet à cette date : programmez plutôt une nouvelle séance.');
    }

    public function test_projet_archive_ni_modification_ni_annulation_ni_retablissement(): void
    {
        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/cancel")->assertOk();
        $this->project->forceFill(['status' => 'archived'])->save();

        $this->actingAs($this->admin)->postJson("/api/sessions/{$this->upcoming->id}/restore")->assertStatus(409);
        $live = ScheduledSession::factory()->create(['project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-10-14']);
        $live->subProjects()->attach($this->groupIds()[0], ['project_id' => $this->project->id]);
        $this->edit($this->payload(), $live)->assertUnprocessable()->assertJsonValidationErrors('project');
        // Ni annulation (#241) : la séance reste programmée.
        $this->actingAs($this->admin)->postJson("/api/sessions/{$live->id}/cancel")
            ->assertStatus(409)->assertJsonPath('message', 'Ce projet est archivé : sa séance ne s’annule plus.');
        $this->assertSame('planned', $live->fresh()->status);
    }

    public function test_reserve_a_l_administratrice(): void
    {
        foreach ([User::factory()->role(UserRole::SuperUser)->create(), $this->referent] as $user) {
            $this->edit($this->payload(), null, $user)->assertForbidden();
            $this->actingAs($user)->postJson("/api/sessions/{$this->upcoming->id}/cancel")->assertForbidden();
            $this->actingAs($user)->postJson("/api/sessions/{$this->upcoming->id}/restore")->assertForbidden();
        }
        $this->actingAs($this->admin)->patchJson('/api/sessions/9999', $this->payload())->assertNotFound();
        $this->actingAs($this->admin)->postJson('/api/sessions/abc/cancel')->assertNotFound();
    }
}
