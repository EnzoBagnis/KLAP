<?php

namespace Tests\Feature\Statistics;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/** Export Excel des statistiques (#219), sur les données de démonstration. */
class StatisticsExportTest extends TestCase
{
    use RefreshDatabase;

    /** L'administratrice. */
    private User $admin;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'mariette.travard@klap.demo')->firstOrFail();
    }

    /**
     * On ouvre le classeur téléchargé.
     *
     * @param  string  $query  les filtres, sous la forme « ?from=… »
     * @return array<string, string> le XML de chaque feuille, par nom d'onglet
     */
    private function workbook(string $query = ''): array
    {
        $response = $this->actingAs($this->admin)->get('/api/statistics/export'.$query)->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'test-xlsx-');
        file_put_contents($path, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Le fichier doit être une archive .xlsx valide.');
        preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $names);
        $sheets = [];
        foreach ($names[1] as $index => $name) {
            $sheets[html_entity_decode($name)] = $zip->getFromName('xl/worksheets/sheet'.($index + 1).'.xml');
        }
        $zip->close();
        unlink($path);

        return $sheets;
    }

    public function test_un_classeur_excel_avec_les_cinq_onglets(): void
    {
        $response = $this->actingAs($this->admin)->get('/api/statistics/export')->assertOk();

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/statistiques-tous-les-projets-\d{4}-\d{2}-\d{2}\.xlsx/', $response->headers->get('Content-Disposition'));
        $this->assertSame(['Synthèse', 'Projets', 'Groupes', 'Provenance', 'Âge', 'Observations'], array_keys($this->workbook()));
        // Le fichier est réservé à l'administration : on vérifie qu'aucun cache partagé ne le garde.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->streamedContent(); // on envoie le fichier temporaire, ce qui le supprime
    }

    public function test_les_chiffres_sont_ceux_de_l_ecran_en_cellules_numeriques(): void
    {
        $project = DB::table('projects')->where('name', 'like', 'Arts Danse au Lycée Charles%')->first();
        $screen = $this->actingAs($this->admin)->getJson("/api/statistics?project_id={$project->id}")->json('data');

        $sheets = $this->workbook("?project_id={$project->id}");

        // « Participants » puis 59, en nombre (pas en texte) : Excel peut calculer.
        $this->assertStringContainsString('<t xml:space="preserve">Participants</t></is></c><c r="B7"><v>59</v></c>', $sheets['Synthèse']);
        $this->assertSame(59, $screen['totals']['participants']);
        $this->assertStringContainsString($project->name, $sheets['Synthèse']);
        // Les groupes à part : la somme de leurs participants redonne le total (22 + 19 + 18 + 0).
        preg_match_all('/<c r="G\d+"><v>(\d+)<\/v><\/c>/', $sheets['Groupes'], $participants);
        $this->assertSame(59, array_sum(array_map('intval', $participants[1])));
        $this->assertStringContainsString('Enthousiasme', $sheets['Observations']);
    }

    public function test_le_nom_de_fichier_reprend_le_projet(): void
    {
        $project = DB::table('projects')->where('name', 'like', 'Arts Danse au Lycée Charles%')->first();

        $response = $this->actingAs($this->admin)->get("/api/statistics/export?project_id={$project->id}")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=statistiques-arts-danse-au-lycee-charles-'.now('Europe/Paris')->format('Y-m-d').'.xlsx');
        $response->streamedContent();
    }

    public function test_un_texte_qui_ressemble_a_une_formule_reste_un_texte(): void
    {
        DB::table('sub_projects')->where('id', 1)->update(['name' => '=HYPERLINK("http://exemple.test","clic")']);

        $sheet = $this->workbook()['Groupes'];

        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;http://exemple.test&quot;,&quot;clic&quot;)</t>', $sheet);
    }

    /**
     * Les cas de « refuse aux intervenants et aux anonymes ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function forbidden(): array
    {
        return ['intervenante' => [UserRole::Contributor]];
    }

    #[DataProvider('forbidden')]
    public function test_refuse_aux_intervenants_et_aux_anonymes(UserRole $role): void
    {
        $this->getJson('/api/statistics/export')->assertUnauthorized();
        $this->actingAs(User::factory()->role($role)->create())->getJson('/api/statistics/export')->assertForbidden();
    }

    public function test_la_super_utilisatrice_exporte_aussi(): void
    {
        $this->actingAs(User::factory()->role(UserRole::SuperUser)->create())->get('/api/statistics/export')->assertOk()->streamedContent();
    }

    public function test_des_filtres_invalides_sont_refuses_comme_a_l_ecran(): void
    {
        $this->actingAs($this->admin)->getJson('/api/statistics/export?project_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }
}
