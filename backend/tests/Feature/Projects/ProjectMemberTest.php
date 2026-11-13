<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Équipe d'un projet (#251, AF7) : ajouter et retirer des intervenant·e·s ponctuel·le·s. */
class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    /** Le projet des tests. */
    private Project $project;

    /** L'intervenante principale du projet. */
    private User $lead;

    /** Marco, intervenant ponctuel. */
    private User $marco;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->project = Project::factory()->create();
        SubProject::factory()->for($this->project)->create();
        $this->lead = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        $this->marco = User::factory()->role(UserRole::Contributor)->create(['name' => 'Marco Delaunay']);
        $this->project->members()->attach($this->lead->id, ['member_role' => 'lead']);
    }

    /**
     * La ligne d'appartenance d'un compte au projet, ou null.
     */
    private function pivot(User $user): ?object
    {
        return DB::table('project_user')->where('project_id', $this->project->id)->where('user_id', $user->id)->first();
    }

    /**
     * On programme une séance du projet pour un·e référent·e, à une date donnée.
     */
    private function sessionFor(User $referent, string $date): ScheduledSession
    {
        return ScheduledSession::factory()->create(['project_id' => $this->project->id, 'referent_id' => $referent->id, 'session_date' => $date]);
    }

    public function test_ajoute_un_ponctuel_qui_peut_ensuite_recevoir_une_seance(): void
    {
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->marco->id])->assertOk()
            ->assertJsonPath('data.team.1.name', 'Marco Delaunay')
            ->assertJsonPath('data.team.1.member_role', 'occasional')
            ->assertJsonPath('data.team.1.end_date', null);
        $this->assertSame('occasional', $this->pivot($this->marco)->member_role);
    }

    public function test_refuse_un_compte_inactif_et_un_doublon(): void
    {
        $inactive = User::factory()->role(UserRole::Contributor)->create(['is_active' => false]);
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $inactive->id])->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'Seul un compte actif peut rejoindre l’équipe.');
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->lead->id])->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'Cette personne fait déjà partie de l’équipe.');
        $this->postJson("/api/projects/{$this->project->id}/members", [])->assertUnprocessable()
            ->assertJsonPath('errors.user_id.0', 'Choisissez l’intervenant·e à ajouter.');
    }

    public function test_retire_un_ponctuel_sans_seance_en_supprimant_le_lien(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);

        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertOk()->assertJsonCount(1, 'data.team');
        $this->assertNull($this->pivot($this->marco));
    }

    public function test_avec_des_seances_passees_l_appartenance_se_termine_aujourd_hui_et_peut_reprendre(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);
        $this->sessionFor($this->marco, '2026-11-02');

        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertOk()
            ->assertJsonPath('data.team.1.end_date', '2026-11-11');
        $this->assertSame('2026-11-11', (string) $this->pivot($this->marco)->end_date);
        // Déjà retiré·e : 404 ; ajouté·e de nouveau : la date de fin est levée.
        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertNotFound();
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->marco->id])->assertOk();
        $this->assertNull($this->pivot($this->marco)->end_date);
        // Une nouvelle période commence aujourd'hui.
        $this->assertSame('2026-11-11', substr((string) $this->pivot($this->marco)->start_date, 0, 10));
    }

    public function test_retire_e_on_ne_voit_plus_le_projet_mais_ses_propres_seances_oui(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);
        $past = $this->sessionFor($this->marco, '2026-11-02');
        $this->sessionFor($this->lead, '2026-11-20');
        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertOk();

        // Le jour du retrait, l'appartenance court encore ; le lendemain, plus de projet.
        Sanctum::actingAs($this->marco);
        $this->getJson("/api/projects/{$this->project->id}")->assertOk();
        $this->travelTo(now()->addDay());
        $this->getJson("/api/projects/{$this->project->id}")->assertNotFound();
        $this->getJson('/api/projects')->assertJsonCount(0, 'data');
        $ids = collect($this->getJson('/api/sessions?from=2026-11-01&to=2026-11-30')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$past->id], $ids->all());
    }

    public function test_une_seance_du_matin_deja_terminee_ne_bloque_pas_et_les_annulees_ne_comptent_pas(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);
        // Il est 10 h : la séance de 8 h à 9 h est passée, celle annulée de cet après-midi ne compte pas.
        ScheduledSession::factory()->create(['project_id' => $this->project->id, 'referent_id' => $this->marco->id, 'session_date' => '2026-11-11', 'start_time' => '08:00', 'end_time' => '09:00']);
        ScheduledSession::factory()->create(['project_id' => $this->project->id, 'referent_id' => $this->marco->id, 'session_date' => '2026-11-11', 'start_time' => '14:00', 'end_time' => '16:00', 'status' => 'cancelled']);

        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertOk();
        $this->assertSame('2026-11-11', (string) $this->pivot($this->marco)->end_date);
    }

    public function test_avec_seulement_des_seances_annulees_le_lien_est_supprime(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);
        ScheduledSession::factory()->create(['project_id' => $this->project->id, 'referent_id' => $this->marco->id, 'session_date' => '2026-11-02', 'status' => 'cancelled']);

        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertOk();
        $this->assertNull($this->pivot($this->marco));
    }

    public function test_refuse_de_retirer_avec_des_seances_a_venir_ou_l_intervenante_principale(): void
    {
        $this->project->members()->attach($this->marco->id, ['member_role' => 'occasional']);
        $this->sessionFor($this->marco, '2026-11-20');
        $this->sessionFor($this->marco, '2026-11-11');

        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->marco->id}")->assertStatus(409)
            ->assertJsonPath('message', 'Marco Delaunay a encore 2 séances à venir sur ce projet : confiez-les à quelqu’un d’autre ou annulez-les avant de la ou le retirer.');
        $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->lead->id}")->assertStatus(409)
            ->assertJsonPath('message', 'C’est l’intervenant·e principal·e : choisissez-en un·e autre dans « Modifier » avant de la ou le retirer.');
    }

    public function test_un_projet_archive_ne_bouge_plus_et_reserve_a_l_administratrice(): void
    {
        $this->project->forceFill(['status' => 'archived'])->save();
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->marco->id])->assertStatus(409);

        $this->project->forceFill(['status' => 'active'])->save();
        foreach ([UserRole::SuperUser, UserRole::Contributor] as $role) {
            Sanctum::actingAs(User::factory()->role($role)->create());
            $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->marco->id])->assertForbidden();
            $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->lead->id}")->assertForbidden();
        }
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->postJson('/api/projects/9999/members', ['user_id' => $this->marco->id])->assertNotFound();
        $this->deleteJson("/api/projects/{$this->project->id}/members/x1")->assertNotFound();
    }
}
