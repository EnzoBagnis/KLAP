<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\LogbookEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le lieu de la séance se corrige depuis la fiche (#215) : « le lieu est modifiable par tout
 * utilisateur » (contexte client, projets en tournée). Mêmes droits que la fiche elle-même.
 */
class LogbookLocationTest extends TestCase
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

    public function test_l_intervenante_corrige_le_lieu_de_sa_seance_depuis_la_fiche(): void
    {
        $id = $this->createEntry();
        $tour = Location::factory()->create(['name' => 'Théâtre de Gap', 'city' => 'Gap', 'postal_code' => '05000']);

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => $tour->id])
            ->assertOk()
            ->assertJsonPath('data.session.location.id', $tour->id)
            ->assertJsonPath('data.session.location.city', 'Gap');

        // C'est la séance qui change : le planning le montre à tout le monde.
        $this->assertSame($tour->id, $this->session->fresh()->location_id);
    }

    public function test_le_lieu_s_enregistre_avec_le_reste_du_brouillon(): void
    {
        $id = $this->createEntry();
        $tour = Location::factory()->create();

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Écoute', 'location_id' => $tour->id])
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Écoute');

        $this->assertSame($tour->id, $this->session->fresh()->location_id);
    }

    public function test_sans_lieu_dans_la_saisie_le_lieu_ne_change_pas(): void
    {
        $before = $this->session->location_id;
        $id = $this->createEntry();

        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Écoute'])->assertOk();

        $this->assertSame($before, $this->session->fresh()->location_id);
    }

    public function test_un_lieu_inactif_ou_inconnu_est_refuse(): void
    {
        $before = $this->session->location_id;
        $id = $this->createEntry();
        $closed = Location::factory()->inactive()->create();

        foreach ([$closed->id, 999999, 'abc'] as $value) {
            $this->actingAs($this->referent)
                ->patchJson("/api/logbook-entries/{$id}", ['location_id' => $value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('location_id');
        }
        $this->assertSame($before, $this->session->fresh()->location_id);
    }

    public function test_un_collegue_du_projet_ne_change_pas_le_lieu(): void
    {
        $before = $this->session->location_id;
        $id = $this->createEntry();
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->actingAs($colleague)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => Location::factory()->create()->id])
            ->assertForbidden();

        $this->assertSame($before, $this->session->fresh()->location_id);
    }

    public function test_une_fiche_verrouillee_ne_change_plus_le_lieu(): void
    {
        $before = $this->session->location_id;
        // Fiche complète, soumise puis verrouillée (contraintes CHECK de MariaDB).
        $id = $this->createEntry($this->completePayload());
        LogbookEntry::query()->whereKey($id)->update(['status' => 'locked', 'submitted_at' => now()]);

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => Location::factory()->create()->id])
            ->assertForbidden();

        $this->assertSame($before, $this->session->fresh()->location_id);
    }

    public function test_l_administration_corrige_aussi_le_lieu_depuis_la_fiche(): void
    {
        $id = $this->createEntry();
        $tour = Location::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::SuperUser)->create())
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => $tour->id])
            ->assertOk();

        $this->assertSame($tour->id, $this->session->fresh()->location_id);
    }

    public function test_le_lieu_actuel_desactive_depuis_ne_bloque_pas_la_fiche(): void
    {
        // La fiche renvoie son lieu à chaque enregistrement : un lieu fermé depuis reste accepté.
        $id = $this->createEntry();
        Location::query()->whereKey($this->session->location_id)->update(['is_active' => false]);

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Écoute', 'location_id' => $this->session->location_id])
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Écoute');
    }

    public function test_un_lieu_vide_est_refuse_avec_un_message_en_francais(): void
    {
        $id = $this->createEntry();

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.location_id.0', 'Choisissez le lieu de la séance.');
        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => Location::factory()->inactive()->create()->id])
            ->assertJsonPath('errors.location_id.0', 'Le lieu choisi n’existe pas ou n’est plus actif.');
    }

    public function test_l_administratrice_corrige_le_lieu(): void
    {
        $id = $this->createEntry();
        $tour = Location::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => $tour->id])
            ->assertOk();

        $this->assertSame($tour->id, $this->session->fresh()->location_id);
    }

    public function test_le_lieu_peut_aussi_partir_avec_l_ouverture_de_la_fiche(): void
    {
        $tour = Location::factory()->create();

        $this->createEntry(['location_id' => $tour->id]);

        $this->assertSame($tour->id, $this->session->fresh()->location_id);
    }

    public function test_une_fiche_d_un_projet_archive_ne_change_plus_le_lieu(): void
    {
        $before = $this->session->location_id;
        $id = $this->createEntry();
        $this->project->update(['status' => 'archived']);

        $this->actingAs($this->referent)
            ->patchJson("/api/logbook-entries/{$id}", ['location_id' => Location::factory()->create()->id])
            ->assertForbidden();

        $this->assertSame($before, $this->session->fresh()->location_id);
    }
}
