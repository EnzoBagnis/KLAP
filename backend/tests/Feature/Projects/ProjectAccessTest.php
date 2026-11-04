<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** RG02 : seule l'administratrice crée et consulte les projets par ces routes. */
class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function routes(): array
    {
        return [
            'liste' => ['getJson', '/api/projects'],
            'détail' => ['getJson', '/api/projects/{id}'],
            'création' => ['postJson', '/api/projects'],
            'types : liste' => ['getJson', '/api/project-types'],
            'types : création' => ['postJson', '/api/project-types'],
            'types : modification' => ['patchJson', '/api/project-types/{type}'],
        ];
    }

    private function callRoute(string $method, string $uri)
    {
        $project = Project::factory()->create();
        $uri = str_replace(['{id}', '{type}'], [(string) $project->id, (string) $project->project_type_id], $uri);

        return match (true) {
            $uri === '/api/projects' && $method === 'postJson' => $this->postJson($uri, [
                'name' => 'Projet test',
                'default_location_id' => Location::factory()->create()->id,
                'principal_user_id' => User::factory()->role(UserRole::Contributor)->create()->id,
            ]),
            $method === 'postJson' => $this->postJson($uri, ['name' => 'Type test']),
            $method === 'patchJson' => $this->patchJson($uri, ['name' => 'Type renommé']),
            default => $this->getJson($uri),
        };
    }

    #[DataProvider('routes')]
    public function test_un_visiteur_non_connecte_est_refuse(string $method, string $uri): void
    {
        $this->callRoute($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('routes')]
    public function test_un_intervenant_est_refuse(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());

        $this->callRoute($method, $uri)->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_la_super_utilisatrice_est_refusee(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::SuperUser)->create());

        $this->callRoute($method, $uri)->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_l_administratrice_passe(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->callRoute($method, $uri)->assertSuccessful();
    }

    public function test_un_refus_ne_cree_aucun_projet(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());

        $this->postJson('/api/projects', [
            'name' => 'Projet refusé',
            'default_location_id' => Location::factory()->create()->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('projects', ['name' => 'Projet refusé']);
    }
}
