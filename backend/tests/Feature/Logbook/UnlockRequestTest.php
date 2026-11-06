<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\KlapNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Demande de réouverture par l'auteur d'une fiche verrouillée (#130, repris de la branche de Lucas, #121). */
class UnlockRequestTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    private User $admin;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->project->update(['name' => 'Arts Danse']);
        $this->referent->update(['name' => 'Léa Fournier']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->superUser = User::factory()->role(UserRole::SuperUser)->create();
    }

    /** Fiche soumise, verrouillée par la commande le 20 octobre. */
    private function lockedEntry(): int
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->artisan('logbook:lock-expired');
        KlapNotification::query()->delete(); // notifications de soumission, hors sujet ici

        return $id;
    }

    private function request(int|string $id, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->referent)->postJson("/api/logbook-entries/{$id}/unlock-request");
    }

    private function requests()
    {
        return KlapNotification::query()->where('type', 'unlock_request')->get();
    }

    public function test_l_auteur_demande_la_reouverture_aux_administratrices(): void
    {
        $id = $this->lockedEntry();
        $other = User::factory()->role(UserRole::Admin)->create();
        $inactive = User::factory()->role(UserRole::Admin)->create(['is_active' => false]);

        $this->request($id)
            ->assertOk()
            ->assertJsonPath('data.unlock_requested_at', now()->toIso8601String())
            ->assertJsonPath('data.unlock_requestable', false);

        $this->assertEqualsCanonicalizing([$this->admin->id, $other->id], $this->requests()->pluck('recipient_id')->all());
        $this->assertSame($id, $this->requests()->first()->logbook_entry_id);
        $this->assertStringStartsWith('Léa Fournier demande à rouvrir la fiche de la séance du 5 octobre : Arts Danse (', $this->requests()->first()->message);
        $this->assertFalse($this->requests()->pluck('recipient_id')->contains($inactive->id));
        $this->assertFalse($this->requests()->pluck('recipient_id')->contains($this->superUser->id));
    }

    public function test_une_fiche_hors_delai_non_encore_verrouillee_peut_etre_demandee(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0)); // brouillon hors délai

        $this->request($id)->assertOk();
        $this->assertCount(1, $this->requests());
    }

    public function test_la_fiche_expose_si_la_demande_est_possible(): void
    {
        $id = $this->lockedEntry();

        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlock_requestable', true)
            ->assertJsonPath('data.unlock_requested_at', null);
        $this->actingAs($this->admin)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlock_requestable', false);
    }

    public function test_une_seule_demande_en_attente(): void
    {
        $id = $this->lockedEntry();
        $this->request($id)->assertOk();

        $this->request($id)->assertStatus(409)->assertJsonPath('message', 'Une demande de réouverture est déjà en attente pour cette fiche.');
        $this->assertCount(1, $this->requests());
    }

    public function test_la_reouverture_clot_la_demande_et_une_nouvelle_est_possible_ensuite(): void
    {
        $id = $this->lockedEntry();
        $this->request($id)->assertOk();
        $this->travel(1)->hours();
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();

        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlock_requested_at', null)
            ->assertJsonPath('data.unlock_requestable', false); // modifiable de nouveau

        // Verrouillée de nouveau dix jours plus tard : une nouvelle demande est acceptée.
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 0));
        $this->artisan('logbook:lock-expired');
        $this->request($id)->assertOk();
        $this->assertCount(2, $this->requests());
    }

    public function test_refuse_une_fiche_encore_modifiable(): void
    {
        $id = $this->createEntry();

        $this->request($id)->assertStatus(409)->assertJsonPath('message', 'La fiche n’est pas verrouillée.');
        $this->assertCount(0, $this->requests());
    }

    public static function notAuthor(): array
    {
        return ['administratrice' => ['admin'], 'super-utilisatrice' => ['superUser']];
    }

    #[DataProvider('notAuthor')]
    public function test_seul_l_auteur_peut_demander(string $who): void
    {
        $id = $this->lockedEntry();

        $this->request($id, $this->{$who})->assertForbidden();
        $this->assertCount(0, $this->requests());
    }

    public function test_un_autre_intervenant_ne_voit_pas_la_fiche(): void
    {
        $id = $this->lockedEntry();

        $this->request($id, User::factory()->role(UserRole::Contributor)->create())->assertNotFound();
        $this->request('abc')->assertNotFound();
        $this->request(99999)->assertNotFound();
    }

    public function test_refuse_un_projet_archive(): void
    {
        $id = $this->lockedEntry();
        $this->project->forceFill(['status' => 'archived'])->save();

        $this->request($id)->assertForbidden();
        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlock_requestable', false);
        $this->assertCount(0, $this->requests());
    }

    public function test_refuse_une_seance_annulee(): void
    {
        $id = $this->lockedEntry();
        $this->session->forceFill(['status' => 'cancelled'])->save();

        $this->request($id)->assertForbidden();
        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")->assertJsonPath('data.unlock_requestable', false);
        $this->assertCount(0, $this->requests());
    }

    public function test_une_administratrice_auteure_rouvre_elle_meme(): void
    {
        $this->session->forceFill(['referent_id' => $this->admin->id])->save();
        $this->referent = $this->admin;
        $id = $this->lockedEntry();

        $this->actingAs($this->admin)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlock_requestable', false)
            ->assertJsonPath('data.unlockable', true);
        $this->request($id, $this->admin)->assertForbidden()->assertJsonPath('message', 'Vous pouvez rouvrir cette fiche vous-même.');
        $this->assertCount(0, $this->requests());
    }

    public function test_sans_administratrice_active_la_demande_n_est_pas_tracee(): void
    {
        $id = $this->lockedEntry();
        $this->admin->update(['is_active' => false]);

        $this->request($id)->assertStatus(503);
        $this->assertCount(0, $this->requests());
    }

    public function test_refuse_sans_authentification(): void
    {
        $id = $this->lockedEntry();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/logbook-entries/{$id}/unlock-request")->assertUnauthorized();
    }

    public function test_une_demande_anterieure_a_la_reouverture_n_est_plus_en_attente(): void
    {
        // Cas du seeder : demande le 20, réouverture le 21.
        $id = $this->lockedEntry();
        $this->request($id)->assertOk();
        DB::table('logbook_entries')->where('id', $id)->update(['unlocked_at' => now()->addDay(), 'status' => 'locked']);

        $this->actingAs($this->referent)->getJson("/api/logbook-entries/{$id}")
            ->assertJsonPath('data.unlock_requested_at', null)
            ->assertJsonPath('data.unlock_requestable', true);
    }

    public function test_la_liste_des_fiches_expose_la_demande_sans_requete_par_fiche(): void
    {
        $id = $this->lockedEntry();
        $this->request($id)->assertOk();

        DB::enableQueryLog();
        $this->actingAs($this->referent)->getJson('/api/logbook-entries')
            ->assertJsonPath('data.0.unlock_requested_at', now()->toIso8601String());
        // La date vient du withMax de la requête principale, pas d'une requête à part par fiche.
        $this->assertSame(0, collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select max('))->count());
    }
}
