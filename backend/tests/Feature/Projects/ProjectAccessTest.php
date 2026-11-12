<?php

namespace Tests\Feature\Projects;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RG02 : seule l'administratrice crée des projets et gère les types. La lecture des
 * projets par les autres rôles est testée dans ProjectVisibilityTest (#89).
 */
class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les cas partagés par 2 tests, dont « un visiteur non connecte est refuse ».
     *
     * @return array<string, array<int, mixed>>
     */
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

    /**
     * On appelle une route des projets, sur un projet créé pour l'occasion.
     *
     * @return TestResponse<JsonResponse>
     */
    private function callRoute(string $method, string $uri): TestResponse
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

    /**
     * Les cas partagés par 2 tests, dont « un intervenant est refuse ».
     *
     * @return array<string, array<int, mixed>>
     */
    public static function adminOnlyRoutes(): array
    {
        return array_diff_key(self::routes(), array_flip(['liste', 'détail']));
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_un_intervenant_est_refuse(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());

        $this->callRoute($method, $uri)->assertForbidden();
    }

    #[DataProvider('adminOnlyRoutes')]
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
