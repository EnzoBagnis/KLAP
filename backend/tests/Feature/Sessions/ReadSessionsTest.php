<?php

namespace Tests\Feature\Sessions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lecture du planning (#102) : par projet, par période, selon les droits.
 */
class ReadSessionsTest extends TestCase
{
    use RefreshDatabase;
    use SessionTestHelpers;

    public function test_les_seances_d_un_projet_sont_triees_avec_leurs_groupes(): void
    {
        [$project, $groups] = $this->projectWithTeam();
        $this->sessionFor($project, ['session_date' => '2026-10-12', 'start_time' => '14:00', 'end_time' => '16:00']);
        $this->sessionFor($project, ['session_date' => '2026-10-05', 'start_time' => '10:00', 'end_time' => '12:00'], $groups);
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->getJson("/api/projects/{$project->id}/sessions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.session_date', '2026-10-05')
            ->assertJsonPath('data.0.start_time', '10:00')
            ->assertJsonPath('data.0.end_time', '12:00')
            ->assertJsonCount(2, 'data.0.sub_projects')
            ->assertJsonPath('data.0.project.id', $project->id)
            ->assertJsonPath('data.1.session_date', '2026-10-12');
    }

    /**
     * Les cas de « l administration lit les seances de tout projet ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function readers(): array
    {
        return ['administratrice' => [UserRole::Admin], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('readers')]
    public function test_l_administration_lit_les_seances_de_tout_projet(UserRole $role): void
    {
        [$project] = $this->projectWithTeam();
        $this->sessionFor($project);
        Sanctum::actingAs(User::factory()->role($role)->create());

        $this->getJson("/api/projects/{$project->id}/sessions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_intervenant_lit_les_seances_de_ses_projets_seulement(): void
    {
        [$project, , $lead] = $this->projectWithTeam();
        [$other] = $this->projectWithTeam();
        $this->sessionFor($project);
        Sanctum::actingAs($lead);
        config(['app.debug' => false]); // comme en production : sans la trace, qui distinguerait les deux 404

        $this->getJson("/api/projects/{$project->id}/sessions")->assertOk()->assertJsonCount(1, 'data');
        $hidden = $this->getJson("/api/projects/{$other->id}/sessions")->assertNotFound()->getContent();
        $this->assertSame($hidden, $this->getJson('/api/projects/999999/sessions')->assertNotFound()->getContent());
    }

    public function test_accepte_exactement_366_jours_couverts(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        // Du 1er janvier 2028 au 31 décembre 2028 : 366 jours (année bissextile).
        $this->getJson('/api/sessions?from=2028-01-01&to=2028-12-31')->assertOk();
    }

    public function test_un_identifiant_non_numerique_renvoie_404(): void
    {
        [$project] = $this->projectWithTeam();
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->getJson("/api/projects/{$project->id}abc/sessions")->assertNotFound();
    }

    public function test_un_visiteur_non_connecte_est_refuse(): void
    {
        [$project] = $this->projectWithTeam();

        $this->getJson("/api/projects/{$project->id}/sessions")->assertUnauthorized();
        $this->getJson('/api/sessions?from=2026-10-01&to=2026-10-31')->assertUnauthorized();
    }

    public function test_le_planning_filtre_la_periode_bornes_comprises_et_garde_les_annulees(): void
    {
        [$project] = $this->projectWithTeam();
        $this->sessionFor($project, ['session_date' => '2026-09-30']);
        $this->sessionFor($project, ['session_date' => '2026-10-01']);
        $this->sessionFor($project, ['session_date' => '2026-10-31', 'status' => 'cancelled']);
        $this->sessionFor($project, ['session_date' => '2026-11-01']);
        Sanctum::actingAs(User::factory()->role(UserRole::SuperUser)->create());

        $this->getJson('/api/sessions?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.session_date', '2026-10-01')
            ->assertJsonPath('data.1.status', 'cancelled');
    }

    public function test_le_planning_d_un_intervenant_couvre_ses_projets_et_ses_seances_de_referent(): void
    {
        [$mine, , $lea] = $this->projectWithTeam();
        [$elsewhere] = $this->projectWithTeam();
        [$foreign] = $this->projectWithTeam();
        $this->sessionFor($mine, ['session_date' => '2026-10-05']);
        // Référente d'une séance d'un projet où elle n'est pas (ou plus) affectée.
        $this->sessionFor($elsewhere, ['session_date' => '2026-10-06', 'referent_id' => $lea->id]);
        $this->sessionFor($foreign, ['session_date' => '2026-10-07']);
        Sanctum::actingAs($lea);

        $dates = collect($this->getJson('/api/sessions?from=2026-10-01&to=2026-10-31')->assertOk()->json('data.*.session_date'));

        $this->assertSame(['2026-10-05', '2026-10-06'], $dates->all());
    }

    /**
     * Les cas de « refuse une periode invalide ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPeriods(): array
    {
        return [
            'début manquant' => ['to=2026-10-31', 'from'],
            'fin manquante' => ['from=2026-10-01', 'to'],
            'format invalide' => ['from=01/10/2026&to=2026-10-31', 'from'],
            'fin avant début' => ['from=2026-10-31&to=2026-10-01', 'to'],
            'plus de 366 jours couverts' => ['from=2026-01-01&to=2027-01-02', 'to'],
            'début en tableau' => ['from[]=2026-10-01&to=2026-10-31', 'from'],
            'fin en tableau' => ['from=2026-10-01&to[]=2026-10-31', 'to'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_refuse_une_periode_invalide(string $query, string $field): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->getJson("/api/sessions?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
    }
}
