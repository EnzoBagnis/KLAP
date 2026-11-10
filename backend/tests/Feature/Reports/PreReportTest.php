<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Logbook\LogbookTestHelpers;
use Tests\TestCase;

/** Pré-bilan Word (#143). */
class PreReportTest extends TestCase
{
    use LogbookTestHelpers;
    use RefreshDatabase;

    private User $admin;

    private Funder $funder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSession();
        $this->project->update(['name' => 'Arts Danse']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->funder = Funder::query()->create(['name' => 'DRAC PACA', 'type' => 'state']);
        $this->project->funders()->attach($this->funder->id);
    }

    protected function tearDown(): void
    {
        // Réponses jamais envoyées en test : deleteFileAfterSend ne passe pas, on nettoie ici.
        foreach (glob(sys_get_temp_dir().'/prebilan*') ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function submitted(array $overrides = []): int
    {
        $id = $this->createEntry($this->completePayload($overrides));
        $this->actingAs($this->referent)->postJson("/api/logbook-entries/{$id}/submit")->assertOk();

        return $id;
    }

    private function download(array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)
            ->get("/api/projects/{$this->project->id}/pre-report?".http_build_query(['funder_id' => $this->funder->id, ...$query]));
    }

    /** Texte du document, dans l'ordre, pour vérifier son contenu. */
    private function text($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'.docx';
        file_put_contents($path, $response->streamedContent());
        $lines = [];
        $walk = function ($element) use (&$walk, &$lines) {
            if ($element instanceof Title) {
                $text = $element->getText();
                $lines[] = is_string($text) ? $text : '';
            } elseif ($element instanceof TextRun) {
                $lines[] = implode('', array_map(fn ($t) => $t instanceof Text ? $t->getText() : '', $element->getElements()));
            } elseif ($element instanceof Text) {
                $lines[] = $element->getText();
            } elseif ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    $cells = [];
                    foreach ($row->getCells() as $cell) {
                        $cells[] = implode('', array_map(fn ($e) => $e instanceof Text ? $e->getText() : '', $cell->getElements()));
                    }
                    $lines[] = implode(' | ', $cells);
                }
            } elseif ($element instanceof AbstractContainer) {
                foreach ($element->getElements() as $child) {
                    $walk($child);
                }
            }
        };
        foreach (IOFactory::load($path)->getSections() as $section) {
            $walk($section);
        }
        unlink($path);

        // Le lecteur de phpword rend le texte encore échappé (&amp;) : le fichier, lui, est correct.
        return html_entity_decode(implode("\n", $lines), ENT_QUOTES | ENT_XML1);
    }

    public function test_produit_un_word_avec_les_chiffres_et_les_observations(): void
    {
        $this->submitted(['highlights' => 'Un duo improvisé très juste.', 'additional_notes' => 'Salle trop petite.',
            'learnings' => 'Écoute du groupe.', 'artist_review' => 'Tensions entre deux élèves.']);

        $response = $this->download()->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringContainsString('pre-bilan-arts-danse-drac-paca-', $response->headers->get('Content-Disposition'));
        $text = $this->text($response);

        $this->assertStringContainsString('Pré-bilan : Arts Danse', $text);
        $this->assertStringContainsString('Financeur : DRAC PACA', $text);
        $this->assertStringContainsString('Participants : 24', $text); // 2 groupes à 12 (maximum puis somme)
        $this->assertStringContainsString('Séances documentées : 1', $text);
        $this->assertStringContainsString('Temps forts et anecdotes : Un duo improvisé très juste.', $text);
        $this->assertStringContainsString('Notes complémentaires : Salle trop petite.', $text);
        // Remarques internes sur des mineurs : jamais envoyées au financeur sans l'accord de la cliente.
        $this->assertStringNotContainsString('Écoute du groupe.', $text);
        $this->assertStringNotContainsString('Tensions entre deux élèves.', $text);
    }

    public function test_les_tableaux_tiennent_dans_la_largeur_de_la_page(): void
    {
        $this->submitted();
        $path = tempnam(sys_get_temp_dir(), 'pre-bilan');
        file_put_contents($path, $this->download()->assertOk()->streamedContent());
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        // A4 (11 906 twips) moins deux marges de 1 440 : 9 026 twips utiles.
        preg_match_all('#<w:tbl>.*?</w:tbl>#s', $xml, $tables);
        $this->assertNotEmpty($tables[0]);
        foreach ($tables[0] as $table) {
            preg_match_all('#<w:gridCol w:w="(\d+)"#', $table, $columns);
            $this->assertLessThanOrEqual(9026, array_sum($columns[1]), 'Un tableau déborde de la page.');
        }
    }

    public function test_ne_reprend_pas_les_reponses_vides(): void
    {
        $this->submitted(['highlights' => 'Rien à signaler.', 'additional_notes' => '']);

        $text = $this->text($this->download()->assertOk());
        $this->assertStringNotContainsString('Temps forts', $text);
        $this->assertStringNotContainsString('Notes complémentaires', $text);
    }

    public function test_une_fiche_sans_observation_n_a_pas_de_titre_vide(): void
    {
        $this->submitted(['highlights' => 'RAS', 'additional_notes' => 'Néant']);

        $text = $this->text($this->download()->assertOk());
        $this->assertStringNotContainsString('lundi 5 octobre 2026', $text);
        $this->assertStringContainsString('Aucune observation à reprendre dans les fiches rendues sur la période.', $text);
        $this->assertStringContainsString('Séances documentées : 1', $text);
    }

    public static function emptyAnswers(): array
    {
        return ['NÉANT' => ['NÉANT'], 'R.A.S.' => ['R.A.S.'], 'ras!' => ['ras!'], 'Rien à signaler !' => ['Rien à signaler !'], '.rien.' => ['.rien.']];
    }

    /** Même jugement que la fiche (#229) : ce qu'elle accepte comme « rien » n'entre pas dans le document. */
    #[DataProvider('emptyAnswers')]
    public function test_les_reponses_vides_sont_ecartees_quelle_que_soit_leur_ecriture(string $highlights): void
    {
        $this->submitted(['highlights' => $highlights]);

        $this->assertStringNotContainsString('Temps forts', $this->text($this->download()->assertOk()));
    }

    public function test_les_caracteres_speciaux_ne_cassent_pas_le_document(): void
    {
        $this->project->update(['name' => 'Arts & Danse <2026>']);
        $this->funder->update(['name' => 'Fondation "Lumière" & Cie']);
        $this->submitted(['highlights' => 'Parents & enfants < 10 ans, "bravo".']);

        $response = $this->download()->assertOk();
        $this->assertStringContainsString('pre-bilan-arts-danse-2026-fondation-lumiere-cie-', $response->headers->get('Content-Disposition'));
        $text = $this->text($response);
        $this->assertStringContainsString('Pré-bilan : Arts & Danse <2026>', $text);
        $this->assertStringContainsString('Financeur : Fondation "Lumière" & Cie', $text);
        $this->assertStringContainsString('Parents & enfants < 10 ans, "bravo".', $text);
    }

    public function test_un_nom_sans_lettre_garde_un_nom_de_fichier_lisible(): void
    {
        $this->project->update(['name' => '¤¤¤']);

        $this->assertStringContainsString('pre-bilan-projet-drac-paca-', $this->download()->assertOk()->headers->get('Content-Disposition'));
    }

    public function test_n_inclut_ni_brouillons_ni_seances_annulees_ni_hors_periode(): void
    {
        $this->createEntry($this->completePayload(['highlights' => 'Brouillon caché']));

        $text = $this->text($this->download()->assertOk());
        $this->assertStringNotContainsString('Brouillon caché', $text);
        $this->assertStringContainsString('Aucune fiche rendue sur la période.', $text);
    }

    public function test_n_inclut_pas_la_fiche_d_une_seance_annulee(): void
    {
        $this->submitted(['highlights' => 'Séance finalement annulée']);
        $this->session->forceFill(['status' => 'cancelled'])->save();

        $text = $this->text($this->download()->assertOk());
        $this->assertStringNotContainsString('Séance finalement annulée', $text);
        $this->assertStringContainsString('Séances documentées : 0', $text);
        $this->assertStringContainsString('Aucune fiche rendue sur la période.', $text);
    }

    public function test_une_seance_sans_groupe_a_un_titre_complet(): void
    {
        $this->submitted(['highlights' => 'Sans groupe']);
        $this->session->subProjects()->detach();

        $text = $this->text($this->download()->assertOk());
        $this->assertStringContainsString("lundi 5 octobre 2026\n", $text);
        $this->assertStringNotContainsString('2026 :', $text);
    }

    public function test_filtre_la_periode(): void
    {
        $this->submitted(['highlights' => 'Séance du 5 octobre']);

        $this->assertStringNotContainsString('Séance du 5 octobre', $this->text($this->download(['from' => '2026-10-06'])->assertOk()));
        $text = $this->text($this->download(['from' => '2026-10-01', 'to' => '2026-10-05'])->assertOk());
        $this->assertStringContainsString('Séance du 5 octobre', $text);
        $this->assertStringContainsString('Période : du 01/10/2026 au 05/10/2026', $text);
        $this->assertStringContainsString('Période : à partir du 01/10/2026', $this->text($this->download(['from' => '2026-10-01'])));
        $this->assertStringContainsString('Période : jusqu’au 31/10/2026', $this->text($this->download(['to' => '2026-10-31'])));
        $this->assertStringContainsString('Période : Toutes les séances', $this->text($this->download()));
    }

    public function test_trace_chaque_export(): void
    {
        $this->download(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertOk();

        $row = DB::table('report_exports')->sole();
        $this->assertSame($this->project->id, $row->project_id);
        $this->assertSame($this->funder->id, $row->funder_id);
        $this->assertSame($this->admin->id, $row->generated_by);
        $this->assertSame('2026-10-01', substr((string) $row->period_start, 0, 10));
        $this->assertSame('2026-10-31', substr((string) $row->period_end, 0, 10));
        $this->assertSame('docx', json_decode($row->options, true)['format']);
    }

    public function test_la_super_utilisatrice_exporte(): void
    {
        $this->download([], User::factory()->role(UserRole::SuperUser)->create())->assertOk();
    }

    public function test_un_intervenant_n_exporte_pas(): void
    {
        $this->download([], $this->referent)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/projects/{$this->project->id}/pre-report?funder_id={$this->funder->id}")->assertUnauthorized();
    }

    public static function invalid(): array
    {
        return [
            'sans financeur' => [['funder_id' => ''], 'funder_id', 'Choisissez le financeur.'],
            'période inversée' => [['from' => '2026-10-10', 'to' => '2026-10-01'], 'to', 'La fin de la période doit être après son début.'],
            'date mal formée' => [['from' => '10/10/2026'], 'from', 'La date doit être au format AAAA-MM-JJ.'],
            'financeur non numérique' => [['funder_id' => 'abc'], 'funder_id', 'Ce financeur ne finance pas ce projet.'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_refuse_des_parametres_invalides(array $query, string $field, string $message): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/pre-report?".http_build_query(['funder_id' => $this->funder->id, ...$query]))
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}.0", $message);
        $this->assertSame(0, DB::table('report_exports')->count());
    }

    public function test_refuse_un_financeur_qui_ne_finance_pas_le_projet(): void
    {
        $other = Funder::query()->create(['name' => 'Ville de Marseille', 'type' => 'local_authority']);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/pre-report?funder_id={$other->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.funder_id.0', 'Ce financeur ne finance pas ce projet.');
    }

    public function test_signale_toutes_les_erreurs_a_la_fois(): void
    {
        $other = Funder::query()->create(['name' => 'Ville de Marseille', 'type' => 'local_authority']);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/pre-report?funder_id={$other->id}&from=2026-10-10&to=2026-10-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['funder_id', 'to']);
    }

    public function test_projet_inexistant(): void
    {
        $this->actingAs($this->admin)->getJson("/api/projects/9999/pre-report?funder_id={$this->funder->id}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/api/projects/abc/pre-report?funder_id={$this->funder->id}")->assertNotFound();
    }
}
