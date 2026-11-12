<?php

namespace Tests\Feature\Sessions;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programmation d'une séance (#102, #168), réservée à l'administratrice.
 */
class StoreSessionTest extends TestCase
{
    use RefreshDatabase;
    use SessionTestHelpers;

    /** L'administratrice. */
    private User $admin;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        Sanctum::actingAs($this->admin);
    }

    /**
     * Une demande de programmation valide.
     *
     * @param  Collection<int, SubProject>  $groups  les groupes concernés
     * @param  array<string, mixed>  $overrides  ce qu'on change dans la demande
     * @return array<string, mixed>
     */
    private function payload(Collection $groups, User $referent, array $overrides = []): array
    {
        return array_merge([
            'session_date' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_type' => 'practice_workshop',
            'location_id' => Location::factory()->create()->id,
            'referent_id' => $referent->id,
            'sub_project_ids' => $groups->pluck('id')->all(),
        ], $overrides);
    }

    public function test_refuse_une_seance_qui_chevauche_une_autre_du_meme_intervenant(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))->assertCreated();

        // Doublon exact, puis chevauchement partiel : refusés, sans rien créer.
        foreach ([['10:00', '12:00'], ['11:30', '13:00'], ['09:00', '10:30']] as [$start, $end]) {
            $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, ['start_time' => $start, 'end_time' => $end]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.start_time.0', 'Cet·te intervenant·e a déjà une séance de 10:00 à 12:00 ce jour-là ('.$project->name.').');
        }
        $this->assertSame(1, ScheduledSession::query()->count());
    }

    public function test_accepte_les_seances_accolees_annulees_ou_d_un_autre_intervenant(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))->assertCreated();

        // Accolée : de 12 h à 14 h, juste après.
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, ['start_time' => '12:00', 'end_time' => '14:00']))->assertCreated();
        // Un autre jour.
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, ['session_date' => '2026-10-06']))->assertCreated();
        // Une autre intervenante au même moment.
        $other = User::factory()->role(UserRole::Contributor)->create();
        $project->members()->attach($other->id, ['member_role' => 'occasional']);
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $other))->assertCreated();
        // La séance annulée libère le créneau.
        ScheduledSession::query()->where('referent_id', $lead->id)->where('start_time', 'like', '10:00%')->whereDate('session_date', '2026-10-05')
            ->update(['status' => 'cancelled']);
        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))->assertCreated();
    }

    public function test_programme_une_seance_transversale(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();

        $response = $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, ['status' => 'done']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.referent.id', $lead->id)
            ->assertJsonCount(2, 'data.sub_projects');

        $session = ScheduledSession::findOrFail($response->json('data.id'));
        $this->assertSame($this->admin->id, $session->created_by);
        $this->assertSame($project->id, $session->project_id);
        foreach ($groups as $group) {
            $this->assertDatabaseHas('scheduled_session_sub_project', [
                'scheduled_session_id' => $session->id, 'sub_project_id' => $group->id, 'project_id' => $project->id,
            ]);
        }
    }

    public function test_accepte_le_type_autre_avec_sa_precision_et_une_date_passee(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, [
            'session_date' => '2026-09-01',
            'session_type' => 'other',
            'session_type_detail' => 'Rencontre avec les parents',
        ]))->assertCreated()->assertJsonPath('data.session_type_detail', 'Rencontre avec les parents');
    }

    /**
     * Les cas de « seule l administratrice programme ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function forbiddenRoles(): array
    {
        return ['super-utilisatrice' => [UserRole::SuperUser], 'intervenant' => [UserRole::Contributor]];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_seule_l_administratrice_programme(UserRole $role): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();
        Sanctum::actingAs(User::factory()->role($role)->create());

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))->assertForbidden();
        $this->assertSame(0, ScheduledSession::count());
    }

    public function test_un_projet_inexistant_renvoie_404(): void
    {
        [, $groups, $lead] = $this->projectWithTeam();

        $this->postJson('/api/projects/999999/sessions', $this->payload($groups, $lead))->assertNotFound();
    }

    public function test_refuse_un_projet_archive(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam(['status' => 'archived']);

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project');
    }

    /**
     * Les cas de « refuse une saisie invalide sans rien creer ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPayloads(): array
    {
        return [
            'fin égale au début' => [fn () => ['end_time' => '10:00'], 'end_time'],
            'fin avant le début' => [fn () => ['end_time' => '09:00'], 'end_time'],
            'heure mal formée' => [fn () => ['start_time' => '10h'], 'start_time'],
            'date absente' => [fn () => ['session_date' => null], 'session_date'],
            'type inconnu' => [fn () => ['session_type' => 'atelier'], 'session_type'],
            'autre sans précision' => [fn () => ['session_type' => 'other'], 'session_type_detail'],
            'précision trop longue' => [fn () => ['session_type' => 'other', 'session_type_detail' => str_repeat('a', 101)], 'session_type_detail'],
            'lieu inexistant' => [fn () => ['location_id' => 999999], 'location_id'],
            'lieu inactif' => [fn () => ['location_id' => Location::factory()->inactive()->create()->id], 'location_id'],
            'référent non affecté' => [fn () => ['referent_id' => User::factory()->role(UserRole::Contributor)->create()->id], 'referent_id'],
            'aucun groupe' => [fn () => ['sub_project_ids' => []], 'sub_project_ids'],
            'groupe d’un autre projet' => [fn () => ['sub_project_ids' => [SubProject::factory()->create()->id]], 'sub_project_ids.0'],
            'début en tableau' => [fn () => ['start_time' => ['10:00']], 'start_time'],
            'précision sans le type autre' => [fn () => ['session_type_detail' => 'inutile'], 'session_type_detail'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_refuse_une_saisie_invalide_sans_rien_creer(\Closure $overrides, string $field): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, $overrides()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, ScheduledSession::count());
        $this->assertDatabaseCount('scheduled_session_sub_project', 0);
    }

    public function test_refuse_un_referent_dont_l_affectation_ne_couvre_pas_la_date(): void
    {
        [$project, $groups] = $this->projectWithTeam();
        $former = User::factory()->role(UserRole::Contributor)->create();
        $project->members()->attach($former->id, ['member_role' => 'occasional', 'end_date' => '2026-09-30']);

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $former, ['session_date' => '2026-10-05']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.referent_id.0', 'L’intervenant·e n’est pas affecté·e au projet à cette date.');
    }

    /**
     * Les cas de « accepte un projet en brouillon ou termine ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function openStatuses(): array
    {
        return ['brouillon' => ['draft'], 'terminé' => ['finished']];
    }

    #[DataProvider('openStatuses')]
    public function test_accepte_un_projet_en_brouillon_ou_termine(string $status): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam(['status' => $status]);

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead))->assertCreated();
    }

    public function test_un_identifiant_non_numerique_renvoie_404(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();

        $this->postJson("/api/projects/{$project->id}abc/sessions", $this->payload($groups, $lead))->assertNotFound();
    }

    public function test_refuse_un_referent_inactif_un_groupe_inactif_et_les_doublons(): void
    {
        [$project, $groups, $lead] = $this->projectWithTeam();
        $lead->update(['is_active' => false]);
        $inactive = SubProject::factory()->for($project)->create(['is_active' => false]);

        $this->postJson("/api/projects/{$project->id}/sessions", $this->payload($groups, $lead, [
            'sub_project_ids' => [$groups[0]->id, $groups[0]->id, $inactive->id],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['referent_id', 'sub_project_ids.0', 'sub_project_ids.2'])
            ->assertJsonPath('errors.referent_id.0', 'L’intervenant·e doit être un compte actif affecté au projet.');
    }
}
