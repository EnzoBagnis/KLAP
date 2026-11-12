<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\KlapNotification;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Logbook\LogbookTestHelpers;
use Tests\TestCase;

/** Tableau de bord (#146). Aujourd'hui : 6 octobre 2026 ; séance du référent : 5 octobre. */
class DashboardTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    /** L'administratrice. */
    private User $admin;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    /**
     * On lit le tableau de bord, par défaut celui de l'intervenant·e référent·e.
     *
     * @return TestResponse<JsonResponse>
     */
    private function dashboard(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->referent)->getJson('/api/dashboard')->assertOk();
    }

    /**
     * On programme une séance du projet pour un·e référent·e, à une date donnée.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function sessionFor(User $referent, string $date, array $attributes = [], ?Project $project = null): ScheduledSession
    {
        return ScheduledSession::factory()->create([
            'project_id' => ($project ?? $this->project)->id,
            'referent_id' => $referent->id,
            'session_date' => $date,
        ] + $attributes);
    }

    /**
     * On ouvre la fiche de la séance courante, complète, et on la soumet.
     *
     * @return int l'identifiant de la fiche
     */
    private function submit(): int
    {
        $id = $this->createEntry($this->completePayload());
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        return $id;
    }

    public function test_les_fiches_a_remplir_sont_les_seances_passees_du_referent_dans_le_delai(): void
    {
        $today = $this->sessionFor($this->referent, '2026-10-06', ['start_time' => '18:00', 'end_time' => '19:30']);
        $this->sessionFor($this->referent, '2026-09-25'); // J+11 : plus de fiche possible
        $this->sessionFor($this->referent, '2026-10-07'); // à venir
        $this->sessionFor($this->referent, '2026-10-04', ['status' => 'cancelled']);
        $this->sessionFor(User::factory()->role(UserRole::Contributor)->create(), '2026-10-05'); // pas la mienne
        $this->sessionFor($this->referent, '2026-10-03', [], Project::factory()->create(['status' => 'archived']));

        $response = $this->dashboard()->assertJsonCount(2, 'to_fill');
        $this->assertSame([$this->session->id, $today->id], array_column($response->json('to_fill'), 'id'));
        $response->assertJsonPath('to_fill.0.logbook_entry', null)
            ->assertJsonPath('to_fill.0.deadline', '2026-10-15')
            ->assertJsonPath('to_fill.1.deadline', '2026-10-16')
            ->assertJsonPath('to_fill.0.project.id', $this->project->id);
    }

    public function test_un_brouillon_reste_a_remplir_une_fiche_rendue_non(): void
    {
        $id = $this->createEntry();
        $this->dashboard()->assertJsonCount(1, 'to_fill')
            ->assertJsonPath('to_fill.0.logbook_entry.id', $id)
            ->assertJsonPath('to_fill.0.logbook_entry.status', 'draft');

        $this->actingAs($this->referent)->patchJson("/api/logbook-entries/{$id}", $this->completePayload())->assertOk();
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();
        $this->dashboard()->assertJsonCount(0, 'to_fill');
    }

    public function test_un_brouillon_hors_delai_ne_figure_plus(): void
    {
        $this->createEntry();
        $this->travelTo(now()->setDate(2026, 10, 16));

        $this->dashboard()->assertJsonCount(0, 'to_fill');
    }

    public function test_un_brouillon_rouvert_revient_avec_sa_nouvelle_echeance(): void
    {
        $id = $this->createEntry();
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();

        $this->dashboard()->assertJsonCount(1, 'to_fill')
            ->assertJsonPath('to_fill.0.logbook_entry.id', $id)
            ->assertJsonPath('to_fill.0.deadline', '2026-10-30');
    }

    public function test_les_prochaines_seances_suivent_les_droits_du_planning(): void
    {
        $mine = $this->sessionFor($this->referent, '2026-10-08');
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $sameProject = $this->sessionFor($colleague, '2026-10-07');
        $this->sessionFor($colleague, '2026-10-09', [], Project::factory()->create()); // projet où je n'interviens pas
        $this->sessionFor($this->referent, '2026-10-10', ['status' => 'cancelled']);

        $response = $this->dashboard()->assertJsonCount(2, 'upcoming');
        $this->assertSame([$sameProject->id, $mine->id], array_column($response->json('upcoming'), 'id'));

        $this->dashboard($this->admin)->assertJsonCount(3, 'upcoming');
    }

    public function test_cinq_prochaines_seances_au_plus(): void
    {
        foreach (range(10, 16) as $day) {
            $this->sessionFor($this->referent, "2026-10-{$day}");
        }

        $this->dashboard()->assertJsonCount(5, 'upcoming')->assertJsonPath('upcoming.0.session_date', '2026-10-10');
    }

    public function test_les_dernieres_fiches_rendues_respectent_les_droits_de_lecture(): void
    {
        $id = $this->submit();
        $colleague = User::factory()->role(UserRole::Contributor)->create();
        $outsider = User::factory()->role(UserRole::Contributor)->create();
        $this->project->members()->attach($colleague->id, ['member_role' => 'occasional']);

        $this->dashboard($colleague)->assertJsonCount(1, 'recent_entries')
            ->assertJsonPath('recent_entries.0.id', $id)
            ->assertJsonPath('recent_entries.0.author.id', $this->referent->id)
            ->assertJsonPath('recent_entries.0.session.session_date', '2026-10-05')
            ->assertJsonPath('recent_entries.0.session.project.id', $this->project->id)
            ->assertJsonCount(2, 'recent_entries.0.session.sub_projects');
        $this->dashboard($outsider)->assertJsonCount(0, 'recent_entries');
        $this->dashboard($this->admin)->assertJsonCount(1, 'recent_entries');
    }

    public function test_un_brouillon_n_est_pas_une_derniere_fiche(): void
    {
        $this->createEntry();

        $this->dashboard($this->admin)->assertJsonCount(0, 'recent_entries');
    }

    public function test_le_suivi_est_reserve_a_l_administration(): void
    {
        $this->sessionFor($this->referent, '2026-09-20'); // passée, hors délai, sans fiche

        $this->dashboard()->assertJsonPath('follow_up', null);
        $this->dashboard($this->admin)
            ->assertJsonPath('follow_up.entries_missing', 2)
            ->assertJsonPath('follow_up.entries_overdue', 1)
            ->assertJsonPath('follow_up.entries_to_fill', 1)
            ->assertJsonPath('follow_up.unlock_requests', 0);
        // La super-utilisatrice ne rouvre pas de fiche : pas de demandes à traiter.
        $this->dashboard(User::factory()->role(UserRole::SuperUser)->create())
            ->assertJsonPath('follow_up.entries_missing', 2)
            ->assertJsonPath('follow_up.unlock_requests', null);
    }

    public function test_compte_les_demandes_de_reouverture_en_attente(): void
    {
        $id = $this->submit();
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/unlock-request")->assertOk();
        $this->assertSame(1, KlapNotification::query()->where('type', 'unlock_request')->count());
        $this->dashboard($this->admin)->assertJsonPath('follow_up.unlock_requests', 1);

        $this->travel(1)->minutes();
        $this->actingAs($this->admin)->postJson("/api/logbook-entries/{$id}/unlock")->assertOk();
        $this->dashboard($this->admin)->assertJsonPath('follow_up.unlock_requests', 0);
    }

    public function test_la_date_du_jour_est_celle_de_paris(): void
    {
        $this->createEntry();
        $tomorrow = $this->sessionFor($this->referent, '2026-10-16');
        // 22 h 30 UTC le 15 : il est déjà 0 h 30 le 16 à Paris, le brouillon (échéance le 15) est hors délai.
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(22, 30));

        $this->dashboard()->assertJsonCount(1, 'to_fill')->assertJsonPath('to_fill.0.id', $tomorrow->id);
    }

    public function test_les_dernieres_fiches_les_plus_recentes_d_abord_verrouillees_comprises(): void
    {
        $first = $this->submit();
        DB::table('logbook_entries')->where('id', $first)->update(['status' => 'locked']);
        $ids = [];
        foreach (range(1, 5) as $day) {
            $this->travel(1)->minutes();
            $session = $this->sessionFor($this->referent, '2026-10-0'.$day);
            $session->subProjects()->attach($this->groupIds()[0], ['project_id' => $this->project->id]);
            $this->session = $session;
            $ids[] = $this->submit();
        }

        $response = $this->dashboard($this->admin)->assertJsonCount(5, 'recent_entries');
        $this->assertSame(array_reverse($ids), array_column($response->json('recent_entries'), 'id'));
        $this->assertNotContains($first, array_column($response->json('recent_entries'), 'id'));
    }

    public function test_une_demande_sur_une_seance_annulee_ensuite_n_est_plus_a_traiter(): void
    {
        $id = $this->submit();
        User::factory()->role(UserRole::Admin)->create(); // deux administratrices prévenues : une seule demande
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/unlock-request")->assertOk();
        $this->dashboard($this->admin)->assertJsonPath('follow_up.unlock_requests', 1);

        $this->session->forceFill(['status' => 'cancelled'])->save();
        $this->dashboard($this->admin)->assertJsonPath('follow_up.unlock_requests', 0);
    }

    public function test_les_prochaines_seances_excluent_les_projets_archives(): void
    {
        $this->sessionFor($this->referent, '2026-10-08', [], tap(Project::factory()->create(['status' => 'archived']),
            fn (Project $archived) => $archived->members()->attach($this->referent->id, ['member_role' => 'lead'])));

        $this->dashboard()->assertJsonCount(0, 'upcoming');
    }

    public function test_une_administratrice_referente_a_ses_fiches_a_remplir(): void
    {
        $session = $this->sessionFor($this->admin, '2026-10-06', ['start_time' => '14:00', 'end_time' => '16:00']);

        $this->dashboard($this->admin)->assertJsonCount(1, 'to_fill')->assertJsonPath('to_fill.0.id', $session->id);
    }

    public function test_connexion_requise(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
