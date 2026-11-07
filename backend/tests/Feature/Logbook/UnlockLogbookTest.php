<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Déverrouillage d'une fiche par l'administratrice (#109). */
class UnlockLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    /** Fiche soumise, verrouillée par la commande le 20 octobre. */
    private function lockedEntry(): int
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->artisan('logbook:lock-expired');

        return $id;
    }

    public function test_rouvre_une_fiche_verrouillee_pour_dix_jours_et_trace_l_operation(): void
    {
        $id = $this->lockedEntry();

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.edit_deadline', '2026-10-30')
            ->assertJsonPath('data.unlocked_by.id', $this->admin->id)
            ->assertJsonPath('data.unlockable', false);

        $row = DB::table('logbook_entries')->find($id);
        $this->assertSame($this->admin->id, $row->unlocked_by);
        $this->assertNotNull($row->unlocked_at);

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Corrigé après réouverture.'])
            ->assertOk()
            ->assertJsonPath('data.editable', true);
    }

    public function test_rouvre_un_brouillon_hors_delai_qui_peut_alors_etre_soumis(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.edit_deadline', '2026-10-30');

        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
    }

    public static function forbidden(): array
    {
        return ['intervenant·e' => [UserRole::Contributor], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('forbidden')]
    public function test_seule_l_administratrice_peut_rouvrir(UserRole $role): void
    {
        $id = $this->lockedEntry();

        $this->actingAs(User::factory()->role($role)->create())
            ->postJson("/api/logbook-entries/{$id}/unlock")->assertForbidden();
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/unlock")->assertForbidden();
        $this->assertSame('locked', DB::table('logbook_entries')->where('id', $id)->value('status'));
    }

    public function test_refuse_sans_authentification(): void
    {
        $id = $this->lockedEntry();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/logbook-entries/{$id}/unlock")->assertUnauthorized();
    }

    public function test_fiche_inexistante(): void
    {
        $this->actingAs($this->admin)->postJson('/api/logbook-entries/999/unlock')->assertNotFound();
        $this->actingAs($this->admin)->postJson('/api/logbook-entries/abc/unlock')->assertNotFound();
    }

    public function test_refuse_une_fiche_encore_modifiable(): void
    {
        $id = $this->createEntry();

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La fiche n’est pas verrouillée.');
    }

    public function test_refuse_quand_la_seance_est_annulee(): void
    {
        $id = $this->lockedEntry();
        $this->session->forceFill(['status' => 'cancelled'])->save();

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertForbidden()
            ->assertJsonPath('message', 'La séance a été annulée ou son projet archivé : la fiche ne se rouvre pas.');
        $this->actingAs($this->admin)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlockable', false);
    }

    public function test_refuse_quand_le_projet_est_archive(): void
    {
        $id = $this->lockedEntry();
        $this->project->forceFill(['status' => 'archived'])->save();

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertForbidden();
    }

    public function test_l_administratrice_modifie_la_fiche_qu_elle_a_rouverte(): void
    {
        $id = $this->lockedEntry();
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();

        // Droits emboîtés (#141) : l'administratrice modifie toutes les fiches non verrouillées.
        $this->actingAs($this->admin)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Corrigé'])->assertOk();
    }

    public function test_unlockable_seulement_pour_l_administratrice_sur_une_fiche_verrouillee(): void
    {
        $id = $this->lockedEntry();
        $superUser = User::factory()->role(UserRole::SuperUser)->create();

        $this->actingAs($this->admin)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlockable', true);
        $this->actingAs($superUser)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlockable', false);
        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlockable', false)
            ->assertJsonPath('data.editable', false);
    }

    public function test_unlockable_faux_dans_les_delais(): void
    {
        $id = $this->createEntry();

        $this->actingAs($this->admin)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlockable', false)
            ->assertJsonPath('data.unlocked_by', null);
    }

    public function test_rouvre_une_fiche_soumise_hors_delai_pas_encore_verrouillee(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0)); // commande pas encore passée

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.edit_deadline', '2026-10-30');

        $this->artisan('logbook:lock-expired')->expectsOutput('0 fiche(s) verrouillée(s).');
        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Corrigé.'])->assertOk();
    }

    public function test_un_second_deverrouillage_immediat_est_refuse(): void
    {
        $id = $this->lockedEntry();
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertStatus(409);
    }

    public function test_seul_le_dernier_deverrouillage_est_trace(): void
    {
        $id = $this->lockedEntry();
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();

        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 0));
        $this->artisan('logbook:lock-expired')->expectsOutput('1 fiche(s) verrouillée(s).');
        $other = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($other)->postJson("/api/logbook-entries/{$id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.unlocked_by.id', $other->id)
            ->assertJsonPath('data.edit_deadline', '2026-11-12');

        $this->assertSame('2026-11-02', substr((string) DB::table('logbook_entries')->where('id', $id)->value('unlocked_at'), 0, 10));
    }

    public function test_un_brouillon_hors_delai_d_une_seance_annulee_ne_se_rouvre_pas(): void
    {
        $id = $this->createEntry();
        $this->session->forceFill(['status' => 'cancelled'])->save();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertForbidden();
    }

    public function test_editable_faux_quand_le_projet_est_archive(): void
    {
        $id = $this->createEntry();
        $this->project->forceFill(['status' => 'archived'])->save();

        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.editable', false);
    }
}
