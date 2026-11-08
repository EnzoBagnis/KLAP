<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * klap:demo (données datées du jour de la démo). La commande recrée la base : pas de
 * RefreshDatabase ici, et on laisse un schéma vide aux tests suivants.
 */
class PrepareDemoTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function statuses(): array
    {
        return DB::table('logbook_entries')->orderBy('id')->pluck('status', 'id')->all();
    }

    public function test_decale_les_donnees_au_jour_de_la_demo_sans_changer_leurs_etats(): void
    {
        // Référence : le jeu de données tel qu'il est le 21/09/2026.
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(9, 0));
        $this->artisan('migrate:fresh', ['--seed' => true]);
        $this->artisan('logbook:lock-expired');
        $reference = $this->statuses();
        $firstSession = DB::table('scheduled_sessions')->where('id', 1)->value('session_date');

        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(9, 0));
        $this->artisan('klap:demo', ['--date' => '2026-11-19', '--force' => true])->assertSuccessful();

        // 59 jours plus tard : mêmes états, dates décalées.
        $this->assertSame($reference, $this->statuses());
        $this->assertSame('2026-09-08', substr((string) $firstSession, 0, 10));
        $this->assertSame('2026-11-06', substr((string) DB::table('scheduled_sessions')->where('id', 1)->value('session_date'), 0, 10));

        // Les 59 participants de la cliente sont toujours là.
        $admin = User::query()->where('email', 'mariette.travard@klap.demo')->firstOrFail();
        $project = DB::table('projects')->where('name', 'Arts Danse au Lycée Charles')->first();
        $this->actingAs($admin)->getJson("/api/statistics?project_id={$project->id}")
            ->assertOk()->assertJsonPath('data.projects.0.participants', 59);
    }

    public function test_lea_a_une_seance_du_jour_a_remplir_en_direct(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(9, 0));
        $this->artisan('klap:demo', ['--date' => '2026-11-19', '--force' => true])->assertSuccessful();

        $lea = User::query()->where('email', 'lea.fournier@klap.demo')->firstOrFail();
        $toFill = $this->actingAs($lea)->getJson('/api/dashboard')->assertOk()->json('to_fill');
        $today = collect($toFill)->firstWhere('session_date', '2026-11-19');
        $this->assertNotNull($today);
        $this->assertNull($today['logbook_entry']);

        // La fiche s'ouvre bien : c'est l'étape 1 de la démo.
        $this->actingAs($lea)->postJson("/api/sessions/{$today['id']}/logbook-entry")->assertCreated();
    }

    public function test_demande_confirmation_et_refuse_une_date_invalide(): void
    {
        $this->artisan('migrate:fresh');
        $this->artisan('klap:demo', ['--date' => '19/11/2026', '--force' => true])->assertFailed();
        $this->artisan('klap:demo')
            ->expectsConfirmation(sprintf('Toute la base « %s » va être effacée puis remplie avec les données de démonstration. Continuer ?', DB::connection()->getDatabaseName()), 'no')
            ->assertFailed();
        $this->assertSame(0, DB::table('users')->count());
    }
}
