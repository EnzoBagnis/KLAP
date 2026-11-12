<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * klap:demo (données datées du jour de la démo). La commande recrée la base : on n'utilise
 * donc pas RefreshDatabase ici, et on laisse un schéma vide aux tests suivants.
 */
class PrepareDemoTest extends TestCase
{
    /**
     * On remet en état ce que les tests ont modifié.
     */
    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    /**
     * Le statut de chaque fiche, par identifiant.
     *
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return DB::table('logbook_entries')->orderBy('id')->pluck('status', 'id')->all();
    }

    public function test_decale_les_donnees_au_jour_de_la_demo_sans_changer_leurs_etats(): void
    {
        // Notre référence : le jeu de données tel qu'il est le 21/09/2026.
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

        // Un projet garde son année scolaire.
        $this->assertSame('2026-09-01', substr((string) DB::table('projects')->where('name', 'Arts Danse au Lycée Charles')->value('start_date'), 0, 10));

        // Les messages de la cloche citent la date décalée, au format de l'application.
        $request = DB::table('klap_notifications')->where('type', 'unlock_request')->first();
        $this->assertNotNull($request);
        $this->assertStringNotContainsString('septembre', $request->message);
        $this->assertStringContainsString('demande à rouvrir la fiche de la séance du', $request->message);

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

    public function test_le_matin_la_seance_du_jour_est_deja_terminee(): void
    {
        // 9 h 40 à Paris (8 h 40 UTC) : la séance se termine à 9 h 30, la fiche peut être soumise.
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(8, 40));
        $this->artisan('klap:demo', ['--force' => true])->assertSuccessful();

        $session = DB::table('scheduled_sessions')->whereDate('session_date', '2026-11-19')
            ->where('referent_id', User::query()->where('email', 'lea.fournier@klap.demo')->value('id'))->first();
        $this->assertSame('07:30', substr((string) $session->start_time, 0, 5));
        $this->assertSame('09:30', substr((string) $session->end_time, 0, 5));
    }

    public function test_l_apres_midi_la_seance_du_jour_reste_de_10_h_a_12_h(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(13, 0));
        $this->artisan('klap:demo', ['--force' => true])->assertSuccessful();

        $session = DB::table('scheduled_sessions')->whereDate('session_date', '2026-11-19')
            ->where('referent_id', User::query()->where('email', 'lea.fournier@klap.demo')->value('id'))->first();
        $this->assertSame('10:00', substr((string) $session->start_time, 0, 5));
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

    public function test_refuse_de_tourner_en_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('klap:demo', ['--force' => true])
            ->expectsOutputToContain('refusé en production')
            ->assertFailed();
        // On revient à l'environnement de test : le tearDown relance migrate:fresh, qui
        // demanderait une confirmation en production.
        $this->app->detectEnvironment(fn () => 'testing');
    }
}
