<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\KlapNotification;
use App\Models\LogbookEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Notification « fiche remplie » et cloche (#122, repris de la branche de Lucas, #121). */
class NotificationTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /** L'administratrice. */
    private User $admin;

    /** La super-utilisatrice. */
    private User $superUser;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->project->update(['name' => 'Arts Danse']);
        $this->referent->update(['name' => 'Léa Fournier']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->superUser = User::factory()->role(UserRole::SuperUser)->create();
    }

    /**
     * On ouvre la fiche de la séance, complète, et on la soumet.
     *
     * @return int l'identifiant de la fiche
     */
    private function submit(): int
    {
        $id = $this->createEntry($this->completePayload());
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        return $id;
    }

    /**
     * Les notifications reçues par un compte.
     *
     * @return Collection<int, KlapNotification>
     */
    private function notificationsOf(User $user): Collection
    {
        return KlapNotification::query()->where('recipient_id', $user->id)->get();
    }

    public function test_la_premiere_soumission_previent_l_administration(): void
    {
        $id = $this->submit();

        foreach ([$this->admin, $this->superUser] as $user) {
            $notifications = $this->notificationsOf($user);
            $this->assertCount(1, $notifications);
            $this->assertSame('entry_submitted', $notifications[0]->type);
            $this->assertSame($id, $notifications[0]->logbook_entry_id);
            $this->assertSame(sprintf('Léa Fournier a rempli la fiche de la séance du 5 octobre : Arts Danse (%s).', $this->session->subProjects()->pluck('name')->join(', ')), $notifications[0]->message);
            $this->assertNull($notifications[0]->read_at);
        }
        $this->assertCount(0, $this->notificationsOf($this->referent));
    }

    public function test_les_comptes_desactives_et_les_intervenants_ne_recoivent_rien(): void
    {
        $inactive = User::factory()->role(UserRole::Admin)->create(['is_active' => false]);
        $otherContributor = User::factory()->role(UserRole::Contributor)->create();

        $this->submit();

        $this->assertCount(0, $this->notificationsOf($inactive));
        $this->assertCount(0, $this->notificationsOf($otherContributor));
    }

    public function test_l_auteur_membre_de_l_administration_n_est_pas_prevenu(): void
    {
        // Séance animée par l'administratrice elle-même.
        $this->session->forceFill(['referent_id' => $this->admin->id])->save();
        $this->referent = $this->admin;
        $this->submit();

        $this->assertCount(0, $this->notificationsOf($this->admin));
        $this->assertCount(1, $this->notificationsOf($this->superUser));
    }

    public function test_une_super_utilisatrice_desactivee_ne_recoit_rien(): void
    {
        $this->superUser->update(['is_active' => false]);
        $this->submit();

        $this->assertCount(0, $this->notificationsOf($this->superUser));
        $this->assertCount(1, $this->notificationsOf($this->admin));
    }

    public function test_deux_soumissions_simultanees_ne_previennent_qu_une_fois(): void
    {
        $id = $this->createEntry($this->completePayload());
        // Une autre soumission passe entre la lecture de la fiche et son écriture.
        $done = false;
        LogbookEntry::retrieved(function (LogbookEntry $entry) use (&$done, $id) {
            if (! $done && $entry->id === $id) {
                $done = true;
                DB::table('logbook_entries')->where('id', $id)->update(['status' => 'submitted', 'submitted_at' => now()]);
            }
        });

        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->assertSame(0, KlapNotification::query()->count());
    }

    public function test_une_seconde_soumission_ne_previent_plus(): void
    {
        $id = $this->submit();
        // Fiche rouverte puis soumise de nouveau : pas de seconde notification.
        DB::table('logbook_entries')->where('id', $id)->update(['status' => 'draft']);
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->assertSame(2, KlapNotification::query()->count());
    }

    public function test_une_soumission_refusee_ne_previent_pas(): void
    {
        $id = $this->createEntry($this->completePayload(['exercises' => ['Un seul']]));
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertUnprocessable();

        $this->assertSame(0, KlapNotification::query()->count());
    }

    public function test_un_nom_de_projet_tres_long_ne_fait_pas_echouer_la_soumission(): void
    {
        // On prend des noms aux longueurs maximales des colonnes (150 et 255) : sans troncature,
        // le message dépasserait 255 caractères.
        $this->project->update(['name' => str_repeat('p', 150)]);
        $this->referent->update(['name' => str_repeat('n', 120)]);
        $this->submit();

        $this->assertLessThanOrEqual(255, mb_strlen($this->notificationsOf($this->admin)[0]->message));
    }

    public function test_chacun_ne_liste_que_ses_notifications(): void
    {
        $this->submit();
        $this->notificationsOf($this->superUser)->first()->update(['read_at' => now()]);

        $this->actingAs($this->admin)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'entry_submitted')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('meta.unread_count', 1);
        $this->actingAs($this->superUser)->getJson('/api/notifications')->assertJsonPath('meta.unread_count', 0);
        $this->actingAs($this->referent)->getJson('/api/notifications')
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    /** Compteur seul, pour la cloche (#227) : les siennes, non lues, au-delà des 30 listées. */
    public function test_le_compteur_de_la_cloche_ne_compte_que_ses_non_lues(): void
    {
        foreach (range(1, 32) as $index) {
            KlapNotification::query()->create(['recipient_id' => $this->admin->id, 'type' => 'reminder', 'message' => "Rappel {$index}"]);
        }
        KlapNotification::query()->create(['recipient_id' => $this->admin->id, 'type' => 'reminder', 'message' => 'Lue', 'read_at' => now()]);
        KlapNotification::query()->create(['recipient_id' => $this->superUser->id, 'type' => 'reminder', 'message' => 'À une autre']);

        $this->actingAs($this->admin)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['unread_count' => 32]]);
        $this->actingAs($this->referent)->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_le_compteur_exige_une_connexion(): void
    {
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_la_liste_s_arrete_aux_30_plus_recentes(): void
    {
        foreach (range(1, 32) as $index) {
            KlapNotification::query()->create([
                'recipient_id' => $this->admin->id, 'type' => 'reminder', 'message' => "Rappel {$index}",
            ]);
        }

        $this->actingAs($this->admin)->getJson('/api/notifications')
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('data.0.message', 'Rappel 32')
            ->assertJsonPath('meta.unread_count', 32);
    }

    public function test_marquer_une_notification_comme_lue(): void
    {
        $this->submit();
        $notification = $this->notificationsOf($this->admin)->first();

        $first = $this->actingAs($this->admin)->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->json('data.read_at');
        $this->assertNotNull($first);

        $this->travel(5)->minutes();
        $this->actingAs($this->admin)->patchJson("/api/notifications/{$notification->id}/read")
            ->assertJsonPath('data.read_at', $first);
    }

    public function test_on_ne_lit_pas_la_notification_d_un_autre(): void
    {
        $this->submit();
        $other = $this->notificationsOf($this->superUser)->first();

        $this->actingAs($this->admin)->patchJson("/api/notifications/{$other->id}/read")->assertNotFound();
        $this->actingAs($this->admin)->patchJson('/api/notifications/abc/read')->assertNotFound();
        $this->actingAs($this->admin)->patchJson('/api/notifications/99999/read')->assertNotFound();
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_tout_marquer_comme_lu_ne_touche_que_ses_notifications(): void
    {
        $this->submit();

        $this->actingAs($this->admin)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('meta.marked', 1)
            ->assertJsonPath('meta.unread_count', 0);
        $this->assertNotNull($this->notificationsOf($this->admin)->first()->read_at);
        $this->assertNull($this->notificationsOf($this->superUser)->first()->read_at);
    }

    public function test_refuse_sans_authentification(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->patchJson('/api/notifications/1/read')->assertUnauthorized();
    }

    public function test_un_groupe_unique_au_nom_du_projet_n_est_pas_repete(): void
    {
        $this->session->subProjects()->first()->update(['name' => 'Arts Danse']);
        $this->session->subProjects()->detach($this->session->subProjects()->skip(1)->first()->id);
        $id = $this->createEntry($this->completePayload());

        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->assertSame('Léa Fournier a rempli la fiche de la séance du 5 octobre : Arts Danse.',
            $this->notificationsOf($this->admin)->sole()->message);
    }

    public function test_l_administration_qui_soumet_pour_un_intervenant_n_est_pas_prevenue_d_elle_meme(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->admin->update(['name' => 'Mariette Travard']);

        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->assertCount(0, $this->notificationsOf($this->admin));
        $this->assertCount(0, $this->notificationsOf($this->referent));
        $notification = $this->notificationsOf($this->superUser)->sole();
        $this->assertStringStartsWith('Mariette Travard a rempli la fiche de la séance du 5 octobre', $notification->message);
    }
}
