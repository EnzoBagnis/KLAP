<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\LogbookEntry;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DraftLogbookTest extends TestCase
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

    public function test_le_referent_ouvre_un_brouillon_vide_avec_l_echeance_a_j_plus_10(): void
    {
        $this->actingAs($this->referent)
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.edit_deadline', '2026-10-15')
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.session.session_date', '2026-10-05')
            ->assertJsonCount(2, 'data.session.sub_projects')
            ->assertJsonPath('data.objectives', null);

        $entry = LogbookEntry::firstOrFail();
        $this->assertDatabaseHas('logbook_entry_user', ['logbook_entry_id' => $entry->id, 'user_id' => $this->referent->id, 'member_role' => 'lead']);
    }

    public function test_un_autre_intervenant_n_ouvre_pas_la_fiche(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Contributor)->create())
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertForbidden()
            ->assertJsonPath('message', 'Seul·e l’intervenant·e référent·e de la séance, l’administratrice ou la super-utilisatrice peut ouvrir sa fiche.');
    }

    /**
     * Les cas partagés par 2 tests, dont « l administration ouvre la fiche pour le referent ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function administration(): array
    {
        return ['administratrice' => [UserRole::Admin], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('administration')]
    public function test_l_administration_ouvre_la_fiche_pour_le_referent(UserRole $role): void
    {
        // Cahier des charges : création de fiche « pour elle et pour d'autres utilisateurs » (#141).
        $id = $this->actingAs(User::factory()->role($role)->create())
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertCreated()
            ->assertJsonPath('data.author.id', $this->referent->id)
            ->assertJsonPath('data.editable', true)
            ->json('data.id');

        // Le ou la référent·e la retrouve et la remplit.
        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Écoute'])->assertOk();
        $this->assertDatabaseHas('logbook_entry_user', ['logbook_entry_id' => $id, 'user_id' => $this->referent->id, 'member_role' => 'lead']);
    }

    public function test_une_seule_fiche_par_seance(): void
    {
        $this->createEntry();

        $this->actingAs($this->referent)
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette séance a déjà une fiche.');
    }

    public function test_une_seance_annulee_ou_un_projet_archive_n_ont_pas_de_fiche(): void
    {
        $this->session->forceFill(['status' => 'cancelled'])->save();
        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertUnprocessable()->assertJsonValidationErrors('session');

        $this->session->forceFill(['status' => 'planned'])->save();
        $this->project->update(['status' => 'archived']);
        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertUnprocessable()->assertJsonValidationErrors('session');
    }

    public function test_seance_inconnue_ou_identifiant_invalide_404_et_visiteur_401(): void
    {
        $this->actingAs($this->referent)->postJson('/api/sessions/999999/logbook-entry')->assertNotFound();
        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}x/logbook-entry")->assertNotFound();
    }

    public function test_un_brouillon_se_complete_par_morceaux(): void
    {
        $id = $this->createEntry(['objectives' => 'Premier jet']);

        $this->patchJson("/api/logbook-entries/{$id}", [
            'evaluation' => ['engagement' => 4],
            'exercises' => ['Échauffement', 'Marche', 'Duo'],
            'attendees' => [['sub_project_id' => $this->groupIds()[0], 'count' => 14]],
        ])
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Premier jet')
            ->assertJsonPath('data.evaluation.engagement', 4)
            ->assertJsonPath('data.exercises', ['Échauffement', 'Marche', 'Duo'])
            ->assertJsonPath('data.session.sub_projects.0.attendees', 14);

        // Les exercices envoyés remplacent la liste précédente, dans l'ordre.
        $this->patchJson("/api/logbook-entries/{$id}", ['exercises' => ['Duo', 'Échauffement']])
            ->assertJsonPath('data.exercises', ['Duo', 'Échauffement']);
        $this->assertDatabaseHas('exercises', ['logbook_entry_id' => $id, 'position' => 1, 'label' => 'Duo']);
    }

    /**
     * Les cas de « refuse une valeur hors limites meme en brouillon ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invalidDrafts(): array
    {
        return [
            '11 exercices' => [['exercises' => array_fill(0, 11, 'Exercice')], 'exercises'],
            'exercice vide' => [['exercises' => ['Échauffement', '']], 'exercises.1'],
            'note 0' => [['evaluation' => ['engagement' => 0]], 'evaluation.engagement'],
            'note 6' => [['evaluation' => ['autonomy' => 6]], 'evaluation.autonomy'],
            'objectifs trop longs' => [['objectives' => str_repeat('a', 401)], 'objectives'],
            'sensation inconnue' => [['evaluation' => ['feeling' => 'joie']], 'evaluation.feeling'],
            'autre sans précision, dès le brouillon' => [['evaluation' => ['feeling' => 'other']], 'evaluation.feeling_detail'],
            'présents négatifs' => [['attendees' => [['sub_project_id' => 0, 'count' => -1]]], 'attendees.0.count'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidDrafts')]
    public function test_refuse_une_valeur_hors_limites_meme_en_brouillon(array $payload, string $field): void
    {
        $id = $this->createEntry();

        $this->patchJson("/api/logbook-entries/{$id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_refuse_des_presents_pour_un_groupe_hors_de_la_seance(): void
    {
        $id = $this->createEntry();
        $other = SubProject::factory()->for($this->project)->create();

        $this->patchJson("/api/logbook-entries/{$id}", ['attendees' => [['sub_project_id' => $other->id, 'count' => 3]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendees.0.sub_project_id' => 'Ce groupe ne fait pas partie de la séance.']);
    }

    #[DataProvider('administration')]
    public function test_l_administration_modifie_toutes_les_fiches(UserRole $role): void
    {
        $id = $this->createEntry();

        $this->actingAs(User::factory()->role($role)->create())
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Corrigé par l’administration'])
            ->assertOk()
            ->assertJsonPath('data.objectives', 'Corrigé par l’administration')
            ->assertJsonPath('data.author.id', $this->referent->id);
    }

    public function test_un_intervenant_du_projet_lit_mais_ne_modifie_pas_et_les_autres_ne_voient_rien(): void
    {
        $id = $this->createEntry();
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->actingAs($colleague)->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Seul·e l’auteur·rice de la fiche, l’administratrice ou la super-utilisatrice peut la modifier.');
        $this->actingAs(User::factory()->role(UserRole::Contributor)->create())
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'x'])->assertNotFound();
    }

    public function test_le_verrouillage_vaut_aussi_pour_l_administration(): void
    {
        $id = $this->createEntry();
        $this->travelTo(now()->setDate(2026, 10, 16));

        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('message', 'La fiche est verrouillée : le délai de 10 jours après la séance est passé.');
    }

    public function test_la_fiche_est_verrouillee_apres_l_echeance(): void
    {
        $id = $this->createEntry();
        $this->travelTo(now()->setDate(2026, 10, 16));

        $this->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'trop tard'])
            ->assertForbidden()
            ->assertJsonPath('message', 'La fiche est verrouillée : le délai de 10 jours après la séance est passé.');
        $this->getJson("/api/logbook-entries/{$id}")->assertOk()->assertJsonPath('data.editable', false);
    }

    public function test_l_echeance_se_compte_a_l_heure_de_marseille(): void
    {
        $id = $this->createEntry();
        // 23 h UTC le 15 = 1 h du matin le 16 à Marseille : le délai est passé.
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(23, 0));

        $this->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'trop tard'])->assertForbidden();
    }

    public function test_une_fiche_ne_s_ouvre_plus_apres_l_echeance(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));

        $this->actingAs($this->referent)
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertForbidden()
            ->assertJsonPath('message', 'Le délai de 10 jours après la séance est passé : la fiche ne peut plus être ouverte.');
    }

    public function test_effacer_la_precision_d_une_sensation_autre_est_refuse(): void
    {
        $id = $this->createEntry(['evaluation' => ['feeling' => 'other', 'feeling_detail' => 'Fatigue de fin de trimestre']]);

        $this->patchJson("/api/logbook-entries/{$id}", ['evaluation' => ['feeling_detail' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evaluation.feeling_detail');
        // Déjà enregistrée, la précision n'a pas à être renvoyée.
        $this->patchJson("/api/logbook-entries/{$id}", ['evaluation' => ['feeling' => 'other', 'engagement' => 3]])->assertOk();
    }

    public function test_une_liste_d_exercices_vide_vide_la_liste(): void
    {
        $id = $this->createEntry(['exercises' => ['Échauffement', 'Marche']]);

        $this->patchJson("/api/logbook-entries/{$id}", ['exercises' => []])->assertOk()->assertJsonPath('data.exercises', []);
    }

    public function test_repasser_a_non_fait_efface_l_explication(): void
    {
        $id = $this->createEntry(['adjustment_done' => true, 'adjustment_detail' => 'Temps calme ajouté']);

        $this->patchJson("/api/logbook-entries/{$id}", ['adjustment_done' => false])
            ->assertOk()
            ->assertJsonPath('data.adjustment_detail', null);
    }

    public function test_une_seance_annulee_bloque_sa_fiche(): void
    {
        $id = $this->createEntry();
        $this->session->forceFill(['status' => 'cancelled'])->save();

        $this->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'x'])->assertForbidden();
    }

    public function test_le_dernier_jour_de_l_echeance_reste_modifiable(): void
    {
        $id = $this->createEntry();
        // 21 h UTC = 23 h à Marseille : encore le 15 octobre, dernier jour du délai.
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(21, 0));

        $this->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'juste à temps'])->assertOk();
    }

    public function test_pas_de_fiche_avant_le_jour_de_la_seance(): void
    {
        $this->session->forceFill(['session_date' => '2026-10-07'])->save(); // demain

        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertUnprocessable()
            ->assertJsonPath('errors.session.0', 'La séance n’a pas encore eu lieu : la fiche se remplit à partir du jour de la séance.');
        $this->assertDatabaseCount('logbook_entries', 0);
    }

    public function test_la_fiche_s_ouvre_le_jour_meme_a_l_heure_de_paris(): void
    {
        $this->session->forceFill(['session_date' => '2026-10-07'])->save();
        // 22 h 30 UTC le 6 octobre : il est déjà le 7 à Paris.
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(22, 30));

        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}/logbook-entry")->assertCreated();
    }

    public function test_la_veille_a_23_h_59_a_paris_la_fiche_reste_fermee(): void
    {
        $this->session->forceFill(['session_date' => '2026-10-07'])->save();
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(21, 59)); // 23 h 59 à Paris

        $this->actingAs($this->referent)->postJson("/api/sessions/{$this->session->id}/logbook-entry")->assertUnprocessable();
    }

    public function test_l_administration_ne_cree_pas_la_fiche_d_un_compte_desactive(): void
    {
        $this->referent->update(['is_active' => false]);

        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->postJson("/api/sessions/{$this->session->id}/logbook-entry")
            ->assertUnprocessable()
            ->assertJsonPath('errors.session.0', 'L’intervenant·e référent·e de cette séance n’a plus de compte actif.');
    }

    public function test_l_administration_suit_les_memes_regles_de_creation(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->session->forceFill(['status' => 'cancelled'])->save();
        $this->actingAs($admin)->postJson("/api/sessions/{$this->session->id}/logbook-entry")->assertUnprocessable();

        $this->session->forceFill(['status' => 'planned', 'session_date' => '2026-10-08'])->save();
        $this->actingAs($admin)->postJson("/api/sessions/{$this->session->id}/logbook-entry")->assertUnprocessable(); // séance à venir

        $this->session->forceFill(['session_date' => '2026-09-01'])->save();
        $this->actingAs($admin)->postJson("/api/sessions/{$this->session->id}/logbook-entry")->assertForbidden(); // hors délai
    }
}
