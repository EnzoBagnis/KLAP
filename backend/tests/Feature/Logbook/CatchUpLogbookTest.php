<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\ScheduledSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rattrapage (#269) : la cliente démarre le test en novembre avec un projet commencé en
 * juillet. Jusqu'à la date réglée sur le serveur, on peut ouvrir la fiche d'une séance
 * ancienne ; ensuite, la règle des 10 jours reprend seule.
 */
class CatchUpLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /** Une séance du 10 juillet, bien au-delà des 10 jours. */
    private ScheduledSession $old;

    /**
     * On est le 14 novembre 2026, le jour où la cliente démarre.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(9, 0));
        $this->old = ScheduledSession::factory()->create([
            'project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-07-10',
        ]);
        $this->old->subProjects()->attach($this->groupIds()[0], ['project_id' => $this->project->id]);
    }

    public function test_sans_date_de_rattrapage_une_seance_ancienne_n_a_plus_de_fiche(): void
    {
        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->old->id}/logbook-entry")->assertForbidden();
        $this->actingAs($this->referent)->getJson('/api/dashboard')->assertJsonCount(0, 'to_fill');
    }

    public function test_pendant_le_rattrapage_la_fiche_s_ouvre_se_remplit_et_se_soumet(): void
    {
        config(['app.catch_up_until' => '2026-11-30']);

        $this->actingAs($this->referent)->getJson('/api/dashboard')
            ->assertJsonPath('to_fill.0.id', $this->old->id)
            ->assertJsonPath('to_fill.0.deadline', '2026-11-30');
        $this->actingAs($this->referent)->getJson('/api/sessions?from=2026-07-01&to=2026-07-31')
            ->assertJsonPath('data.0.entry_deadline', '2026-11-30');

        $id = $this->actingAs($this->referent)->postJson("/api/sessions/{$this->old->id}/logbook-entry")
            ->assertCreated()->assertJsonPath('data.edit_deadline', '2026-11-30')->json('data.id');
        $payload = $this->completePayload(['attendees' => [['sub_project_id' => $this->groupIds()[0], 'count' => 12]]]);
        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", $payload)->assertOk();
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    public function test_le_dernier_jour_compte_et_le_lendemain_la_regle_des_dix_jours_reprend(): void
    {
        config(['app.catch_up_until' => '2026-11-30']);
        // 22 h à Paris le 30 novembre : c'est encore le dernier jour.
        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(21, 0));
        $id = $this->actingAs($this->referent)->postJson("/api/sessions/{$this->old->id}/logbook-entry")->assertCreated()->json('data.id');

        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(9, 0));
        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Trop tard'])->assertForbidden();
        $other = ScheduledSession::factory()->create([
            'project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-08-20',
        ]);
        $this->actingAs($this->referent)->postJson("/api/sessions/{$other->id}/logbook-entry")->assertForbidden();
    }

    public function test_une_seance_recente_garde_ses_dix_jours_meme_apres_la_date_de_rattrapage(): void
    {
        config(['app.catch_up_until' => '2026-11-30']);
        $recent = ScheduledSession::factory()->create([
            'project_id' => $this->project->id, 'referent_id' => $this->referent->id, 'session_date' => '2026-11-27',
        ]);

        $this->travelTo(now()->setDate(2026, 12, 5)->setTime(9, 0));
        $this->actingAs($this->referent)->postJson("/api/sessions/{$recent->id}/logbook-entry")
            ->assertCreated()->assertJsonPath('data.edit_deadline', '2026-12-07');
    }

    public function test_pendant_le_rattrapage_une_seance_ancienne_sans_fiche_se_modifie_encore_et_n_est_pas_hors_delai(): void
    {
        config(['app.catch_up_until' => '2026-11-30']);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->getJson('/api/dashboard')->assertJsonPath('follow_up.entries_overdue', 0);
        $this->actingAs($admin)->patchJson("/api/sessions/{$this->old->id}", [
            'session_date' => '2026-07-11', 'start_time' => '10:00', 'end_time' => '12:00', 'session_type' => 'practice_workshop',
            'location_id' => $this->old->location_id, 'referent_id' => $this->referent->id, 'sub_project_ids' => [$this->groupIds()[0]],
        ])->assertOk()->assertJsonPath('data.session_date', '2026-07-11');
    }

    public function test_une_date_mal_ecrite_est_ignoree(): void
    {
        config(['app.catch_up_until' => '30/11/2026']);

        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->old->id}/logbook-entry")->assertForbidden();
    }
}
