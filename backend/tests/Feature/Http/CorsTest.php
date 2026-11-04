<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Le frontend, sur une autre origine, doit pouvoir lire Retry-After (réponses 429). */
class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-cors', fn () => response()->json(['ok' => true], 429, ['Retry-After' => '42']));
    }

    public function test_retry_after_est_expose_a_une_autre_origine(): void
    {
        $this->getJson('/api/test-cors', ['Origin' => 'http://localhost:5173'])
            ->assertStatus(429)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After')
            ->assertHeader('Retry-After', '42');
    }

    public function test_la_requete_prealable_est_acceptee(): void
    {
        $this->call('OPTIONS', '/api/test-cors', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            // Le jeton Bearer passe par Authorization : il doit rester autorisé.
            ->assertHeader('Access-Control-Allow-Headers', 'authorization,content-type');
    }
}
