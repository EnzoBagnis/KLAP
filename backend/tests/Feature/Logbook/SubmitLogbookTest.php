<?php

namespace Tests\Feature\Logbook;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmitLogbookTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
    }

    public function test_soumet_une_fiche_complete_qui_sort_des_fiches_manquantes(): void
    {
        $id = $this->createEntry($this->completePayload());

        $this->postJson("/api/logbook-entries/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.editable', true);

        $this->assertNotNull(DB::table('logbook_entries')->where('id', $id)->value('submitted_at'));
        $this->assertSame(0, DB::table('v_missing_entries')->where('scheduled_session_id', $this->session->id)->count());
    }

    public function test_non_fait_n_exige_aucune_explication_et_fait_en_exige_une(): void
    {
        $id = $this->createEntry($this->completePayload(['adjustment_done' => true, 'adjustment_detail' => 'Ajout d’un temps calme']));
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
    }

    public static function incomplete(): array
    {
        return [
            'un seul exercice' => [['exercises' => ['Échauffement']], 'exercises'],
            'fait sans explication' => [['adjustment_done' => true], 'adjustment_detail'],
            'explication de 4 caractères' => [['adjustment_done' => true, 'adjustment_detail' => 'abcd'], 'adjustment_detail'],
            'ajustement non renseigné' => [['adjustment_done' => null], 'adjustment_done'],
            'sans évolution' => [['evaluation' => ['progress' => null]], 'evaluation.progress'],
            'sans note d’autonomie' => [['evaluation' => ['autonomy' => null]], 'evaluation.autonomy'],
            'sans sensation' => [['evaluation' => ['feeling' => null]], 'evaluation.feeling'],
            'sans interaction' => [['evaluation' => ['interaction' => null]], 'evaluation.interaction'],
            'sans objectifs' => [['objectives' => null], 'objectives'],
            'temps forts trop courts' => [['highlights' => 'ok'], 'highlights'],
            'temps forts : ponctuation seule' => [['highlights' => '...'], 'highlights'],
            'sans « à reprendre »' => [['next_steps' => null], 'next_steps'],
        ];
    }

    public static function nothingToReport(): array
    {
        // Moins de 5 caractères : refusés avant le correctif.
        return ['rien' => ['rien'], 'Rien' => ['Rien'], 'RAS' => ['RAS'], 'RAS.' => ['RAS.'], 'ras!' => ['ras!']];
    }

    /** « rien » est accepté quand il n'y a pas eu de temps fort, comme le dit le message (#225). */
    #[DataProvider('nothingToReport')]
    public function test_rien_est_accepte_dans_les_temps_forts(string $highlights): void
    {
        $id = $this->createEntry($this->completePayload(['highlights' => $highlights]));

        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
    }

    public function test_une_fiche_rendue_avec_rien_reste_modifiable(): void
    {
        $id = $this->createEntry($this->completePayload(['highlights' => 'Rien']));
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->patchJson("/api/logbook-entries/{$id}", ['objectives' => 'Écoute du groupe'])->assertOk();
    }

    #[DataProvider('incomplete')]
    public function test_refuse_une_fiche_incomplete(array $overrides, string $field): void
    {
        $id = $this->createEntry($this->completePayload($overrides));

        $this->postJson("/api/logbook-entries/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
        $this->assertSame('draft', DB::table('logbook_entries')->where('id', $id)->value('status'));
    }

    public function test_exige_les_presents_de_chaque_groupe(): void
    {
        $payload = $this->completePayload();
        $payload['attendees'] = [['sub_project_id' => $this->groupIds()[0], 'count' => 12]];
        $id = $this->createEntry($payload);

        $this->postJson("/api/logbook-entries/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('errors.attendees.0', 'Indiquez le nombre de présents de chaque groupe (1 au moins).');
    }

    public function test_refuse_de_soumettre_avant_la_fin_de_la_seance(): void
    {
        // Séance de 10 h à 12 h le 5 octobre : à 11 h 30 (heure de Marseille), elle n'est pas finie.
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 30));
        $id = $this->createEntry($this->completePayload());

        $this->postJson("/api/logbook-entries/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        // À 12 h 30 à Marseille (10 h 30 UTC), elle est terminée.
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 30));
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
    }

    public function test_une_fiche_soumise_reste_modifiable_mais_complete_jusqu_a_l_echeance(): void
    {
        $id = $this->createEntry($this->completePayload());
        $this->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        $this->patchJson("/api/logbook-entries/{$id}", ['highlights' => 'Le duo final, repris par tout le groupe.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
        $this->patchJson("/api/logbook-entries/{$id}", ['exercises' => ['Un seul']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exercises');
        $this->patchJson("/api/logbook-entries/{$id}", ['adjustment_done' => true, 'adjustment_detail' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('adjustment_detail');
        $this->patchJson("/api/logbook-entries/{$id}", ['exercises' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exercises');
    }

    public function test_l_administration_peut_soumettre_et_pas_un_autre_intervenant(): void
    {
        $id = $this->createEntry($this->completePayload());
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->actingAs($colleague)->postJson("/api/logbook-entries/{$id}/submit")->assertForbidden();
        $this->actingAs(User::factory()->role(UserRole::Contributor)->create())
            ->postJson("/api/logbook-entries/{$id}/submit")->assertNotFound();
        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->postJson("/api/logbook-entries/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    }
}
