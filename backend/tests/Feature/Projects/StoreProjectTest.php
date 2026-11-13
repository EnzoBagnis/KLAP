<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\Location;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Création d'un projet (#55, écran 58 de la maquette), réservée à l'administratrice.
 */
class StoreProjectTest extends TestCase
{
    use RefreshDatabase;

    /** L'administratrice. */
    private User $admin;

    /** Le lieu des tests. */
    private Location $location;

    /** Le type de projet des tests. */
    private ProjectType $type;

    /** Léa, intervenante. */
    private User $lea;

    /**
     * On prépare ce que tous les tests de la classe partagent.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->location = Location::factory()->create(['name' => 'Grand studio', 'is_klap' => true]);
        $this->type = ProjectType::factory()->create(['name' => 'Partenariat lycée']);
        $this->lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        Sanctum::actingAs($this->admin);
    }

    /**
     * Une demande de création valide.
     *
     * @param  array<string, mixed>  $overrides  ce qu'on change dans la demande
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Arts Danse au Lycée Charles',
            'project_type_id' => $this->type->id,
            'planned_sessions' => 32,
            'start_date' => '2026-09-15',
            'end_date' => '2027-06-19',
            'default_location_id' => $this->location->id,
            'principal_user_id' => $this->lea->id,
            'organization' => 'Lycée Saint-Charles',
            'city' => 'Marseille',
            'postal_code' => '13003',
            'sub_projects' => [
                ['name' => 'Seconde 3'],
                ['name' => 'Seconde 7'],
                ['name' => 'Première 2'],
                ['name' => 'Terminale 5'],
            ],
        ], $overrides);
    }

    public function test_cree_le_projet_et_ses_sous_projets(): void
    {
        $response = $this->postJson('/api/projects', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Arts Danse au Lycée Charles')
            ->assertJsonPath('data.project_type.name', 'Partenariat lycée')
            ->assertJsonPath('data.planned_sessions', 32)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.default_location.name', 'Grand studio')
            ->assertJsonCount(4, 'data.sub_projects');

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertSame($this->admin->id, $project->created_by);
        $this->assertSame(4, $project->subProjects()->count());
    }

    public function test_recopie_structure_ville_et_code_postal_dans_les_sous_projets(): void
    {
        $this->postJson('/api/projects', $this->payload([
            'sub_projects' => [
                ['name' => 'Seconde 3'],
                ['name' => 'Groupe Izzo', 'organization' => 'Collège Izzo', 'postal_code' => '13002'],
            ],
        ]))->assertCreated();

        $this->assertDatabaseHas('sub_projects', [
            'name' => 'Seconde 3', 'organization' => 'Lycée Saint-Charles',
            'city' => 'Marseille', 'postal_code' => '13003',
        ]);
        // Les valeurs propres au sous-projet l'emportent sur celles du projet.
        $this->assertDatabaseHas('sub_projects', [
            'name' => 'Groupe Izzo', 'organization' => 'Collège Izzo',
            'city' => 'Marseille', 'postal_code' => '13002',
        ]);
    }

    public function test_cree_un_sous_projet_par_defaut_quand_aucun_n_est_fourni(): void
    {
        $payload = $this->payload(['name' => 'Étincelles']);
        unset($payload['sub_projects']);

        $this->postJson('/api/projects', $payload)
            ->assertCreated()
            ->assertJsonCount(1, 'data.sub_projects')
            ->assertJsonPath('data.sub_projects.0.name', 'Étincelles')
            ->assertJsonPath('data.sub_projects.0.organization', 'Lycée Saint-Charles');
    }

    public function test_le_groupe_unique_recoit_l_age_et_l_effectif_du_projet(): void
    {
        // Sans eux, nos statistiques n'ont ni tranche d'âge ni assiduité (#244).
        $payload = $this->payload(['name' => 'Étincelles', 'age_range' => '15-16 ans', 'expected_headcount' => 28]);
        unset($payload['sub_projects']);

        $this->postJson('/api/projects', $payload)
            ->assertCreated()
            ->assertJsonCount(1, 'data.sub_projects');
        $this->assertDatabaseHas('sub_projects', ['name' => 'Étincelles', 'age_range' => '15-16 ans', 'expected_headcount' => 28]);
    }

    public function test_les_sous_projets_gardent_leur_age_et_leur_effectif(): void
    {
        $this->postJson('/api/projects', $this->payload([
            'age_range' => '12-13 ans', // celui du projet ne s'applique qu'au groupe unique
            'sub_projects' => [['name' => 'Seconde 3', 'age_range' => '15-16 ans', 'expected_headcount' => 24], ['name' => 'Seconde 7']],
        ]))->assertCreated();

        $this->assertDatabaseHas('sub_projects', ['name' => 'Seconde 3', 'age_range' => '15-16 ans', 'expected_headcount' => 24]);
        $this->assertDatabaseHas('sub_projects', ['name' => 'Seconde 7', 'age_range' => null, 'expected_headcount' => null]);
    }

    public function test_une_liste_vide_donne_aussi_un_sous_projet_par_defaut(): void
    {
        $this->postJson('/api/projects', $this->payload(['sub_projects' => []]))
            ->assertCreated()
            ->assertJsonCount(1, 'data.sub_projects');
    }

    public function test_accepte_le_minimum_nom_lieu_et_principal(): void
    {
        $this->postJson('/api/projects', [
            'name' => 'AIMER',
            'default_location_id' => $this->location->id,
            'principal_user_id' => $this->lea->id,
        ])->assertCreated()->assertJsonPath('data.status', 'active');
    }

    public function test_accepte_le_statut_brouillon(): void
    {
        $this->postJson('/api/projects', $this->payload(['status' => 'draft']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_ignore_created_by_envoye_dans_la_requete(): void
    {
        $other = User::factory()->create();

        $id = $this->postJson('/api/projects', $this->payload(['created_by' => $other->id]))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($this->admin->id, Project::findOrFail($id)->created_by);
    }

    /**
     * Les cas de « refuse une saisie invalide sans rien creer ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPayloads(): array
    {
        return [
            'nom absent' => [['name' => null], 'name'],
            'nom trop long' => [['name' => str_repeat('a', 151)], 'name'],
            'lieu absent' => [['default_location_id' => null], 'default_location_id'],
            'principal absent' => [['principal_user_id' => null], 'principal_user_id'],
            'principal inexistant' => [['principal_user_id' => 999999], 'principal_user_id'],
            'financeurs pas en liste' => [['funder_ids' => 'DRAC'], 'funder_ids'],
            'financeur inexistant' => [['funder_ids' => [999999]], 'funder_ids.0'],
            'principal non entier' => [['principal_user_id' => 'abc'], 'principal_user_id'],
            'financeur non entier' => [['funder_ids' => ['abc']], 'funder_ids.0'],
            'lieu inexistant' => [['default_location_id' => 999999], 'default_location_id'],
            'fin avant début' => [['start_date' => '2027-06-19', 'end_date' => '2026-09-15'], 'end_date'],
            'statut archivé' => [['status' => 'archived'], 'status'],
            'statut terminé' => [['status' => 'finished'], 'status'],
            'zéro séance prévue' => [['planned_sessions' => 0], 'planned_sessions'],
            'code postal du projet invalide' => [['postal_code' => '1300'], 'postal_code'],
            'code postal de sous-projet invalide' => [
                ['sub_projects' => [['name' => 'Seconde 3', 'postal_code' => '13 003']]],
                'sub_projects.0.postal_code',
            ],
            'sous-projet sans nom' => [['sub_projects' => [['class_group' => 'Seconde']]], 'sub_projects.0.name'],
            'sous-projets en double' => [
                ['sub_projects' => [['name' => 'Seconde 3'], ['name' => 'seconde 3']]],
                'sub_projects.0.name',
            ],
            'type inexistant' => [['project_type_id' => 999999], 'project_type_id'],
            'séances non entières' => [['planned_sessions' => 'abc'], 'planned_sessions'],
            'séances hors limite' => [['planned_sessions' => 65536], 'planned_sessions'],
            'début invalide' => [['start_date' => '31/02/2026'], 'start_date'],
            'statut nul' => [['status' => null], 'status'],
            'lieu non entier' => [['default_location_id' => 'grand-studio'], 'default_location_id'],
            'structure trop longue' => [['organization' => str_repeat('a', 151)], 'organization'],
            'ville trop longue' => [['city' => str_repeat('a', 101)], 'city'],
            'nom de sous-projet trop long' => [
                ['sub_projects' => [['name' => str_repeat('a', 151)]]],
                'sub_projects.0.name',
            ],
            'classe trop longue' => [
                ['sub_projects' => [['name' => 'Seconde 3', 'class_group' => str_repeat('a', 101)]]],
                'sub_projects.0.class_group',
            ],
            'tranche d’âge trop longue' => [
                ['sub_projects' => [['name' => 'Seconde 3', 'age_range' => str_repeat('a', 31)]]],
                'sub_projects.0.age_range',
            ],
            'effectif nul' => [
                ['sub_projects' => [['name' => 'Seconde 3', 'expected_headcount' => 0]]],
                'sub_projects.0.expected_headcount',
            ],
            'effectif du groupe unique négatif' => [['sub_projects' => [], 'expected_headcount' => -1], 'expected_headcount'],
            'âge du groupe unique trop long' => [['sub_projects' => [], 'age_range' => str_repeat('a', 31)], 'age_range'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_refuse_une_saisie_invalide_sans_rien_creer(array $overrides, string $field): void
    {
        $this->postJson('/api/projects', $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, Project::count());
        $this->assertDatabaseCount('sub_projects', 0);
    }

    public function test_refuse_un_lieu_desactive(): void
    {
        $inactive = Location::factory()->inactive()->create();

        $this->postJson('/api/projects', $this->payload(['default_location_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_location_id');

        $this->assertSame(0, Project::count());
        $this->assertDatabaseCount('sub_projects', 0);
    }

    public function test_accepte_une_fin_le_jour_du_debut(): void
    {
        $this->postJson('/api/projects', $this->payload([
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
        ]))->assertCreated();
    }

    public function test_refuse_un_type_desactive(): void
    {
        $inactive = ProjectType::factory()->inactive()->create();

        $this->postJson('/api/projects', $this->payload(['project_type_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.project_type_id.0', 'Le type de projet choisi n’existe pas ou n’est plus actif.');
    }

    public function test_accepte_un_projet_sans_type(): void
    {
        $this->postJson('/api/projects', $this->payload(['project_type_id' => null]))
            ->assertCreated()
            ->assertJsonPath('data.project_type', null);
    }

    public function test_affecte_le_principal_et_les_financeurs(): void
    {
        [$drac, $ville] = Funder::factory()->count(2)->create();

        $id = $this->postJson('/api/projects', $this->payload(['funder_ids' => [$drac->id, $ville->id]]))
            ->assertCreated()
            ->assertJsonCount(1, 'data.team')
            ->assertJsonPath('data.team.0.name', 'Léa Fournier')
            ->assertJsonPath('data.team.0.member_role', 'lead')
            ->assertJsonCount(2, 'data.funders')
            ->json('data.id');

        $this->assertDatabaseHas('project_user', ['project_id' => $id, 'user_id' => $this->lea->id, 'member_role' => 'lead']);
        $this->assertDatabaseHas('funder_project', ['project_id' => $id, 'funder_id' => $drac->id]);
        $this->assertDatabaseHas('funder_project', ['project_id' => $id, 'funder_id' => $ville->id]);
    }

    public function test_accepte_un_projet_sans_financeur(): void
    {
        $this->postJson('/api/projects', $this->payload())
            ->assertCreated()
            ->assertJsonCount(0, 'data.funders');
        $this->postJson('/api/projects', $this->payload(['name' => 'Autre projet', 'funder_ids' => []]))
            ->assertCreated()
            ->assertJsonCount(0, 'data.funders');
        $this->postJson('/api/projects', $this->payload(['name' => 'Troisième projet', 'funder_ids' => null]))
            ->assertCreated()
            ->assertJsonCount(0, 'data.funders');
    }

    public function test_n_affecte_aucun_ponctuel_a_la_creation(): void
    {
        $other = User::factory()->role(UserRole::Contributor)->create();

        $this->postJson('/api/projects', $this->payload(['occasional_user_ids' => [$other->id]]))
            ->assertCreated()
            ->assertJsonCount(1, 'data.team');

        $this->assertDatabaseMissing('project_user', ['user_id' => $other->id]);
        $this->assertDatabaseCount('project_user', 1);
    }

    /**
     * Les cas de « le principal doit etre un compte actif » : le rôle ne compte pas (#272).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPrincipals(): array
    {
        return [
            'intervenant désactivé' => [['role' => UserRole::Contributor, 'is_active' => false]],
            'administratrice désactivée' => [['role' => UserRole::Admin, 'is_active' => false]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidPrincipals')]
    public function test_le_principal_doit_etre_un_compte_actif(array $attributes): void
    {
        $principal = User::factory()->create($attributes);

        $this->postJson('/api/projects', $this->payload(['principal_user_id' => $principal->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.principal_user_id.0', 'L’intervenant·e principal·e doit être un compte actif.');

        $this->assertSame(0, Project::count());
    }

    public function test_le_message_des_sous_projets_en_double_est_en_francais(): void
    {
        $this->postJson('/api/projects', $this->payload([
            'sub_projects' => [['name' => 'Seconde 3'], ['name' => 'seconde 3']],
        ]))->assertJsonValidationErrors(['sub_projects.0.name' => 'Deux sous-projets portent le même nom.']);
    }

    public function test_refuse_un_financeur_en_double(): void
    {
        $drac = Funder::factory()->create();

        $this->postJson('/api/projects', $this->payload(['funder_ids' => [$drac->id, $drac->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['funder_ids.0' => 'Un même financeur est choisi deux fois.']);

        $this->assertSame(0, Project::count());
        $this->assertDatabaseCount('funder_project', 0);
    }

    public function test_les_messages_d_erreur_sont_en_francais(): void
    {
        $this->postJson('/api/projects', $this->payload([
            'name' => null,
            'start_date' => '2027-06-19',
            'end_date' => '2026-09-15',
        ]))
            ->assertJsonPath('errors.name.0', 'Le champ nom du projet est obligatoire.')
            ->assertJsonPath('errors.end_date.0', 'La date de fin doit être postérieure ou égale à la date de début.');
    }
}
