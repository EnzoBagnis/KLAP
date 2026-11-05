<?php

namespace Tests\Feature\Logbook;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Verrouillage automatique à J+10 (#109) : séance du 5 octobre, échéance le 15 inclus. */
class LockLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
    }

    private function submittedEntry(): int
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        return $id;
    }

    private function statusOf(int $id): string
    {
        return DB::table('logbook_entries')->where('id', $id)->value('status');
    }

    public function test_la_fiche_reste_soumise_le_jour_de_l_echeance_et_se_verrouille_le_lendemain(): void
    {
        $id = $this->submittedEntry();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(21, 0)); // 23 h à Paris
        $this->artisan('logbook:lock-expired')->assertSuccessful();
        $this->assertSame('submitted', $this->statusOf($id));

        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(8, 0));
        $this->artisan('logbook:lock-expired')->expectsOutput('1 fiche(s) verrouillée(s).')->assertSuccessful();
        $this->assertSame('locked', $this->statusOf($id));
    }

    public function test_compte_l_echeance_a_l_heure_de_paris(): void
    {
        $id = $this->submittedEntry();

        // 23 h 30 UTC le 15 : il est déjà le 16 à Paris.
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(23, 30));
        $this->artisan('logbook:lock-expired')->assertSuccessful();
        $this->assertSame('locked', $this->statusOf($id));
    }

    public function test_un_brouillon_hors_delai_reste_brouillon_et_manquant(): void
    {
        $id = $this->createEntry();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->artisan('logbook:lock-expired')->assertSuccessful();

        $this->assertSame('draft', $this->statusOf($id));
        $this->assertSame(1, DB::table('v_missing_entries')->where('scheduled_session_id', $this->session->id)->count());
        $this->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.editable', false);
    }

    public function test_ne_touche_pas_une_fiche_rouverte_dans_son_nouveau_delai(): void
    {
        $id = $this->submittedEntry();
        DB::table('logbook_entries')->where('id', $id)->update(['edit_deadline' => '2026-10-30', 'unlocked_at' => now()]);

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->artisan('logbook:lock-expired')->assertSuccessful();
        $this->assertSame('submitted', $this->statusOf($id));
    }

    public function test_relancer_la_commande_ne_change_rien(): void
    {
        $id = $this->submittedEntry();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));

        $this->artisan('logbook:lock-expired')->expectsOutput('1 fiche(s) verrouillée(s).');
        $this->artisan('logbook:lock-expired')->expectsOutput('0 fiche(s) verrouillée(s).');
        $this->assertSame('locked', $this->statusOf($id));
    }

    public function test_la_commande_est_planifiee_chaque_jour(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'logbook:lock-expired'));

        $this->assertNotNull($event);
        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertSame('Europe/Paris', $event->timezone);
    }
}
