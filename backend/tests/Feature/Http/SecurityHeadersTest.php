<?php

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Durcissement relevé par l'audit de sécurité (#200). */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_reponses_de_l_api_portent_les_en_tetes_de_securite(): void
    {
        $response = $this->getJson('/api/me')->assertUnauthorized();

        foreach (SecurityHeaders::HEADERS as $name => $value) {
            $response->assertHeader($name, $value);
        }
        $response->assertHeaderMissing('X-Powered-By');
        // En HTTP simple (local), pas de HSTS : il n'a de sens qu'en HTTPS.
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_en_https(): void
    {
        $this->getJson('https://localhost/api/me')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_la_racine_repond_en_json_sans_cookie(): void
    {
        $response = $this->get('/')->assertOk()->assertJson(['application' => 'KLAP']);

        $this->assertSame([], $response->headers->getCookies());
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_un_identifiant_non_numerique_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $project = Project::factory()->create();

        $this->getJson("/api/projects/{$project->id}")->assertOk();
        $this->getJson("/api/projects/{$project->id}abc")->assertNotFound();
        $this->getJson("/api/projects/{$project->id}%20OR%201=1")->assertNotFound();
    }
}
