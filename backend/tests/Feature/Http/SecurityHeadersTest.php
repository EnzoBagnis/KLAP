<?php

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    }

    public function test_aucune_route_n_accepte_un_identifiant_non_numerique(): void
    {
        // SQLite ne lit pas « 1abc » comme 1, contrairement à MySQL : on vérifie donc le routeur
        // lui-même, qui ne doit plus laisser passer ces identifiants jusqu'à la base.
        foreach (['/api/projects/1abc', '/api/projects/1%20OR%201=1', '/api/logbook-entries/2x', '/api/sessions/3x/logbook-entry', '/api/accounts/4x'] as $uri) {
            try {
                Route::getRoutes()->match(Request::create($uri, $uri === '/api/accounts/4x' ? 'PATCH' : 'GET'));
                $this->fail("La route {$uri} ne devrait pas exister.");
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }

        // Le jeton d'invitation, lui, reste libre.
        $this->assertSame('api/invitations/{token}', Route::getRoutes()->match(Request::create('/api/invitations/abc-DEF_123'))->uri());
    }

    public function test_une_variable_cors_vide_revient_a_toutes_les_origines(): void
    {
        $this->assertSame(['*'], $this->corsOriginsWith(''));
        $this->assertSame(
            ['https://klap.example.org', 'https://autre.example.org'],
            $this->corsOriginsWith('https://klap.example.org, https://autre.example.org'),
        );
    }

    /**
     * Relit config/cors.php avec CORS_ALLOWED_ORIGINS forcée. Laravel lit $_SERVER, puis $_ENV,
     * puis getenv() : les trois sont forcés, puis rétablis, sinon un .env qui définit déjà la
     * variable (celui de la CI, copié de .env.example) l'emporterait.
     */
    private function corsOriginsWith(string $value): array
    {
        $saved = [$_SERVER['CORS_ALLOWED_ORIGINS'] ?? null, $_ENV['CORS_ALLOWED_ORIGINS'] ?? null, getenv('CORS_ALLOWED_ORIGINS')];
        $_SERVER['CORS_ALLOWED_ORIGINS'] = $_ENV['CORS_ALLOWED_ORIGINS'] = $value;
        putenv("CORS_ALLOWED_ORIGINS={$value}");

        try {
            return (require config_path('cors.php'))['allowed_origins'];
        } finally {
            [$server, $env, $getenv] = $saved;
            if ($server === null) {
                unset($_SERVER['CORS_ALLOWED_ORIGINS']);
            } else {
                $_SERVER['CORS_ALLOWED_ORIGINS'] = $server;
            }
            if ($env === null) {
                unset($_ENV['CORS_ALLOWED_ORIGINS']);
            } else {
                $_ENV['CORS_ALLOWED_ORIGINS'] = $env;
            }
            putenv($getenv === false ? 'CORS_ALLOWED_ORIGINS' : "CORS_ALLOWED_ORIGINS={$getenv}");
        }
    }
}
