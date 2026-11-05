<?php

namespace Tests\Feature\Statistics;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Statistiques (#113). Jeu de données :
 * projet A, groupes G1 et G2 ; projet B, groupe G3.
 *  s1 10/09 A  G1=10 G2=8  soumise  notes 4/3/2
 *  s2 17/09 A  G1=12       verrouillée notes 2/5/4
 *  s3 24/09 A  G1=30       brouillon (ignorée, manquante)
 *  s4 01/10 A  G1=40       séance annulée (ignorée, jamais manquante)
 *  s5 08/10 A  G2=20       sans fiche (manquante)
 *  s6 15/09 B  G3=15       soumise sans évaluation
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $projectA;

    private Project $projectB;

    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->projectA = Project::factory()->create(['name' => 'Projet A']);
        $this->projectB = Project::factory()->create(['name' => 'Projet B']);
        foreach (['G1' => $this->projectA, 'G2' => $this->projectA, 'G3' => $this->projectB] as $name => $project) {
            $this->groups[$name] = SubProject::factory()->for($project)->create(['name' => $name]);
        }

        $this->addSession($this->projectA, '2026-09-10', ['G1' => 10, 'G2' => 8], 'submitted', [4, 3, 2]);
        $this->addSession($this->projectA, '2026-09-17', ['G1' => 12], 'locked', [2, 5, 4]);
        $this->addSession($this->projectA, '2026-09-24', ['G1' => 30], 'draft', [5, 5, 5]);
        $this->addSession($this->projectA, '2026-10-01', ['G1' => 40], null, null, 'cancelled');
        $this->addSession($this->projectA, '2026-10-08', ['G2' => 20], null);
        $this->addSession($this->projectB, '2026-09-15', ['G3' => 15], 'submitted');
    }

    private function addSession(Project $project, string $date, array $attendees, ?string $status, ?array $notes = null, string $sessionStatus = 'planned'): void
    {
        $session = ScheduledSession::factory()->create([
            'project_id' => $project->id, 'session_date' => $date, 'status' => $sessionStatus,
        ]);
        foreach ($attendees as $group => $count) {
            $session->subProjects()->attach($this->groups[$group]->id, ['project_id' => $project->id, 'attendees' => $count]);
        }
        if ($status === null) {
            return;
        }
        $entryId = DB::table('logbook_entries')->insertGetId([
            'scheduled_session_id' => $session->id,
            'author_id' => $session->referent_id,
            'status' => $status,
            'adjustment_done' => false,
            'keep_in_class' => false,
            'edit_deadline' => $date,
            'submitted_at' => $status === 'draft' ? null : $date.' 18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($notes !== null) {
            DB::table('participant_evaluations')->insert([
                'logbook_entry_id' => $entryId,
                'engagement' => $notes[0], 'expression' => $notes[1], 'autonomy' => $notes[2],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function statistics(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson('/api/statistics?'.http_build_query($query))
            ->assertOk()
            ->json('data');
    }

    private function project(array $data, Project $project): array
    {
        return collect($data['projects'])->firstWhere('id', $project->id);
    }

    public function test_participants_maximum_par_groupe_puis_somme_par_projet(): void
    {
        $data = $this->statistics();
        $a = $this->project($data, $this->projectA);

        // G1 : max(10, 12), le brouillon (30) et la séance annulée (40) ne comptent pas ; G2 : 8.
        $this->assertSame(['G1' => 12, 'G2' => 8], collect($a['sub_projects'])->pluck('participants', 'name')->all());
        $this->assertSame(['G1' => 2, 'G2' => 1], collect($a['sub_projects'])->pluck('sessions_documented', 'name')->all());
        $this->assertSame(20, $a['participants']);
        $this->assertSame(2, $a['sessions_documented']);
        $this->assertSame(2, $a['entries_missing']); // brouillon du 24/09 et séance du 08/10
        $this->assertSame(2, $a['evaluations']);
        $this->assertEquals([3.0, 4.0, 3.0], [$a['avg_engagement'], $a['avg_expression'], $a['avg_autonomy']]);

        $this->assertSame(35, $data['totals']['participants']);
        $this->assertSame(3, $data['totals']['sessions_documented']);
        $this->assertSame(2, $data['totals']['entries_missing']);
        $this->assertSame(2, $data['totals']['evaluations']);
    }

    public function test_un_projet_sans_evaluation_a_des_moyennes_nulles(): void
    {
        $b = $this->project($this->statistics(), $this->projectB);

        $this->assertSame(15, $b['participants']);
        $this->assertSame(0, $b['evaluations']);
        $this->assertNull($b['avg_engagement']);
        $this->assertNull($b['avg_autonomy']);
    }

    public function test_les_moyennes_globales_portent_sur_toutes_les_evaluations(): void
    {
        $this->addSession($this->projectB, '2026-09-22', ['G3' => 10], 'submitted', [5, 5, 5]);
        $totals = $this->statistics()['totals'];

        // (4 + 2 + 5) / 3, et non la moyenne des moyennes de projet (3 et 5).
        $this->assertSame(3, $totals['evaluations']);
        $this->assertEquals(3.67, $totals['avg_engagement']);
    }

    public function test_filtre_par_periode_sur_la_date_de_seance_bornes_incluses(): void
    {
        $data = $this->statistics(['from' => '2026-09-15', 'to' => '2026-09-24']);
        $a = $this->project($data, $this->projectA);

        $this->assertSame(12, $a['participants']); // seule s2 (s3 est un brouillon)
        $this->assertSame(1, $a['sessions_documented']);
        $this->assertSame(1, $a['entries_missing']);
        $this->assertEquals(2.0, $a['avg_engagement']);
        $this->assertSame(15, $this->project($data, $this->projectB)['participants']);

        $early = $this->project($this->statistics(['to' => '2026-09-10']), $this->projectA);
        $this->assertSame(18, $early['participants']); // s1 seule : 10 + 8
        $this->assertSame(0, $early['entries_missing']);
    }

    public function test_filtre_par_projet(): void
    {
        $data = $this->statistics(['project_id' => $this->projectB->id]);

        $this->assertSame([$this->projectB->id], array_column($data['projects'], 'id'));
        $this->assertSame(15, $data['totals']['participants']);
        $this->assertSame(0, $data['totals']['entries_missing']);
        $this->assertSame($this->projectB->id, $data['filters']['project_id']);
    }

    public function test_sans_filtre_les_chiffres_sont_ceux_des_vues(): void
    {
        $data = $this->statistics();

        foreach (DB::table('v_project_participants')->get() as $row) {
            $this->assertSame((int) $row->participants, collect($data['projects'])->firstWhere('id', $row->project_id)['participants']);
        }
        foreach (DB::table('v_project_indicators')->get() as $row) {
            $project = collect($data['projects'])->firstWhere('id', $row->project_id);
            $this->assertSame((int) $row->entries, $project['evaluations']);
            $this->assertEquals((float) $row->avg_engagement, $project['avg_engagement']);
            $this->assertEquals((float) $row->avg_expression, $project['avg_expression']);
            $this->assertEquals((float) $row->avg_autonomy, $project['avg_autonomy']);
        }
        $this->assertSame(DB::table('v_missing_entries')->count(), $data['totals']['entries_missing']);

        $groups = collect($data['projects'])->flatMap(fn ($project) => $project['sub_projects'])->keyBy('id');
        foreach (DB::table('v_sub_project_participants')->get() as $row) {
            $this->assertSame((int) $row->participants, $groups[$row->sub_project_id]['participants']);
            $this->assertSame((int) $row->sessions_documented, $groups[$row->sub_project_id]['sessions_documented']);
        }
    }

    public function test_des_presents_non_renseignes_comptent_pour_zero(): void
    {
        $this->addSession($this->projectB, '2026-09-29', ['G3' => null], 'submitted');
        $group = $this->project($this->statistics(), $this->projectB)['sub_projects'][0];

        $this->assertSame(15, $group['participants']); // le maximum ignore la valeur absente
        $this->assertSame(2, $group['sessions_documented']);
    }

    public function test_un_projet_sans_groupe_ni_seance_donne_des_zeros(): void
    {
        $empty = Project::factory()->create(['name' => 'Projet vide']);
        $row = $this->project($this->statistics(), $empty);

        $this->assertSame(0, $row['participants']);
        $this->assertSame(0, $row['sessions_documented']);
        $this->assertSame(0, $row['entries_missing']);
        $this->assertSame([], $row['sub_projects']);
        $this->assertNull($row['avg_expression']);
    }

    public function test_debut_de_periode_seul(): void
    {
        $a = $this->project($this->statistics(['from' => '2026-09-17']), $this->projectA);

        $this->assertSame(12, $a['participants']);
        $this->assertSame(2, $a['entries_missing']);
    }

    public function test_un_projet_non_entier_ne_donne_qu_un_message(): void
    {
        $this->actingAs($this->admin)->getJson('/api/statistics?project_id=abc')
            ->assertUnprocessable()
            ->assertJsonPath('errors.project_id', ['Le projet demandé n’existe pas.']);
    }

    public static function readers(): array
    {
        return ['administratrice' => [UserRole::Admin], 'super-utilisatrice' => [UserRole::SuperUser]];
    }

    #[DataProvider('readers')]
    public function test_lecture_par_l_administration(UserRole $role): void
    {
        $this->actingAs(User::factory()->role($role)->create())->getJson('/api/statistics')->assertOk();
    }

    public function test_refuse_les_intervenants_et_les_anonymes(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Contributor)->create())->getJson('/api/statistics')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/statistics')->assertUnauthorized();
    }

    public static function invalidFilters(): array
    {
        return [
            'date mal formée' => [['from' => '05/10/2026'], 'from'],
            'fin avant le début' => [['from' => '2026-10-10', 'to' => '2026-10-01'], 'to'],
            'projet inexistant' => [['project_id' => 9999], 'project_id'],
            'projet non entier' => [['project_id' => 'abc'], 'project_id'],
            'date en tableau' => [['from' => ['2026-10-01']], 'from'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_refuse_des_filtres_invalides(array $query, string $field): void
    {
        $this->actingAs($this->admin)->getJson('/api/statistics?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    private function describeGroups(): void
    {
        foreach ([
            'G1' => ['city' => 'Marseille', 'postal_code' => '13003', 'age_range' => '15-16 ans', 'expected_headcount' => 15],
            'G2' => ['city' => 'Aix-en-Provence', 'postal_code' => '13100', 'age_range' => '15-16 ans', 'expected_headcount' => null],
            'G3' => ['city' => 'Marseille', 'postal_code' => '13003', 'age_range' => '8-10 ans', 'expected_headcount' => 20],
        ] as $name => $attributes) {
            $this->groups[$name]->forceFill($attributes)->save();
        }
    }

    public function test_provenance_par_ville_et_code_postal_et_participants_par_age(): void
    {
        $this->describeGroups();
        $data = $this->statistics();

        $this->assertSame([
            ['city' => 'Marseille', 'postal_code' => '13003', 'participants' => 27, 'groups' => 2],
            ['city' => 'Aix-en-Provence', 'postal_code' => '13100', 'participants' => 8, 'groups' => 1],
        ], $data['by_city']);
        $this->assertSame([
            ['age_range' => '15-16 ans', 'participants' => 20, 'groups' => 2],
            ['age_range' => '8-10 ans', 'participants' => 15, 'groups' => 1],
        ], $data['by_age_range']);
    }

    public function test_provenance_suit_les_filtres(): void
    {
        $this->describeGroups();
        $data = $this->statistics(['project_id' => $this->projectB->id]);

        $this->assertSame([['city' => 'Marseille', 'postal_code' => '13003', 'participants' => 15, 'groups' => 1]], $data['by_city']);
    }

    public function test_assiduite_presents_moyens_rapportes_a_l_effectif_prevu(): void
    {
        $this->describeGroups();
        $groups = collect($this->statistics()['projects'])->flatMap(fn ($project) => $project['sub_projects'])->keyBy('name');

        $this->assertEquals(11.0, $groups['G1']['average_attendees']); // (10 + 12) / 2
        $this->assertSame(73, $groups['G1']['attendance_rate']); // 11 / 15
        $this->assertSame(15, $groups['G1']['expected_headcount']);
        $this->assertNull($groups['G2']['attendance_rate']); // effectif prévu inconnu
        $this->assertSame(75, $groups['G3']['attendance_rate']);
        $this->assertSame('Aix-en-Provence', $groups['G2']['city']);
    }

    public function test_un_groupe_sans_seance_documentee_n_a_pas_d_assiduite(): void
    {
        $extra = SubProject::factory()->for($this->projectB)->create(['name' => 'G4', 'expected_headcount' => 12]);
        $group = collect($this->project($this->statistics(), $this->projectB)['sub_projects'])->firstWhere('id', $extra->id);

        $this->assertNull($group['average_attendees']);
        $this->assertNull($group['attendance_rate']);
    }

    public function test_separe_les_fiches_en_retard_et_a_remplir(): void
    {
        // Séance du 1er novembre sans fiche : échéance le 11, encore à remplir.
        $this->addSession($this->projectA, '2026-11-01', ['G2' => 5], null);
        // Brouillon d'une séance du 10 octobre rouvert jusqu'au 10 novembre : à remplir.
        $this->addSession($this->projectA, '2026-10-10', ['G2' => 5], 'draft');
        DB::table('logbook_entries')->whereNull('submitted_at')->where('edit_deadline', '2026-10-10')->update(['edit_deadline' => '2026-11-10']);

        $a = $this->project($this->statistics(), $this->projectA);

        // En retard : brouillon du 24/09 (échéance 24/09) et séance du 08/10 sans fiche (échéance 18/10).
        $this->assertSame(4, $a['entries_missing']);
        $this->assertSame(2, $a['entries_overdue']);
        $this->assertSame(2, $a['entries_to_fill']);
        $this->assertSame(2, $this->statistics()['totals']['entries_overdue']);
    }

    public function test_l_echeance_du_jour_reste_a_remplir(): void
    {
        // Séance du 26 octobre : échéance le 5 novembre, jour du test, incluse.
        $this->addSession($this->projectB, '2026-10-26', ['G3' => 5], null);
        $b = $this->project($this->statistics(), $this->projectB);

        $this->assertSame(0, $b['entries_overdue']);
        $this->assertSame(1, $b['entries_to_fill']);
    }

    public function test_des_presents_absents_ne_font_pas_baisser_la_moyenne(): void
    {
        $this->describeGroups();
        $this->addSession($this->projectB, '2026-09-29', ['G3' => null], 'submitted');
        $group = $this->project($this->statistics(), $this->projectB)['sub_projects'][0];

        $this->assertEquals(15.0, $group['average_attendees']);
        $this->assertSame(75, $group['attendance_rate']);
    }

    public function test_le_taux_part_de_la_moyenne_exacte_et_peut_depasser_100(): void
    {
        $this->groups['G3']->forceFill(['expected_headcount' => 4])->save();
        $this->addSession($this->projectB, '2026-09-22', ['G3' => 2], 'submitted');
        $this->addSession($this->projectB, '2026-09-29', ['G3' => 16], 'submitted');
        $this->addSession($this->projectB, '2026-10-06', ['G3' => 0], 'submitted');
        // (15 + 2 + 16 + 0) / 4 = 8,25 ; 8,25 / 4 = 206,25 %, et non 8,3 / 4 = 207,5 %.
        $group = $this->project($this->statistics(), $this->projectB)['sub_projects'][0];

        $this->assertEquals(8.3, $group['average_attendees']);
        $this->assertSame(206, $group['attendance_rate']);
    }

    public function test_provenance_sans_ville_et_meme_ville_avec_deux_codes_postaux(): void
    {
        $this->groups['G1']->forceFill(['city' => 'Marseille', 'postal_code' => '13003', 'age_range' => null])->save();
        $this->groups['G2']->forceFill(['city' => null, 'postal_code' => null, 'age_range' => '16 - 17 ans'])->save();
        $this->groups['G3']->forceFill(['city' => 'Marseille', 'postal_code' => '13001', 'age_range' => '16-17 ans'])->save();
        $data = $this->statistics();

        $this->assertSame([
            ['city' => 'Marseille', 'postal_code' => '13001', 'participants' => 15, 'groups' => 1],
            ['city' => 'Marseille', 'postal_code' => '13003', 'participants' => 12, 'groups' => 1],
            ['city' => null, 'postal_code' => null, 'participants' => 8, 'groups' => 1],
        ], $data['by_city']);
        $this->assertSame([
            ['age_range' => '16-17 ans', 'participants' => 23, 'groups' => 2],
            ['age_range' => null, 'participants' => 12, 'groups' => 1],
        ], $data['by_age_range']);
    }

    public function test_un_brouillon_sans_echeance_retombe_sur_j_plus_10(): void
    {
        $this->addSession($this->projectB, '2026-10-30', ['G3' => 5], 'draft');
        DB::table('logbook_entries')->where('edit_deadline', '2026-10-30')->update(['edit_deadline' => null]);

        $b = $this->project($this->statistics(), $this->projectB);
        $this->assertSame(0, $b['entries_overdue']);
        $this->assertSame(1, $b['entries_to_fill']);
    }

    public function test_les_fiches_hors_delai_suivent_la_periode(): void
    {
        $a = $this->project($this->statistics(['from' => '2026-10-01']), $this->projectA);

        $this->assertSame(1, $a['entries_missing']); // séance du 08/10 seulement
        $this->assertSame(1, $a['entries_overdue']);
    }

    public function test_le_delai_se_compte_a_l_heure_de_paris(): void
    {
        // Séance du 25 octobre : échéance le 4 novembre. À 23 h 30 UTC le 4, il est le 5 à Paris.
        $this->addSession($this->projectB, '2026-10-25', ['G3' => 5], null);

        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(22, 30));
        $this->assertSame(0, $this->project($this->statistics(), $this->projectB)['entries_overdue']);

        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(23, 30));
        $this->assertSame(1, $this->project($this->statistics(), $this->projectB)['entries_overdue']);
    }
}
