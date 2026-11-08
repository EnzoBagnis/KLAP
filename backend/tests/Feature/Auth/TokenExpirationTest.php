<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Durée de vie des jetons (#157) : 30 jours, purge planifiée. */
class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    private function me(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/me');
    }

    public function test_un_jeton_expire_au_bout_de_trente_jours(): void
    {
        $token = User::factory()->role(UserRole::Contributor)->create()->createToken('api')->plainTextToken;

        $this->travel(29)->days();
        $this->me($token)->assertOk();

        $this->travel(2)->days();
        $this->me($token)->assertUnauthorized();
    }

    public function test_la_duree_par_defaut_est_de_trente_jours(): void
    {
        $this->assertSame(60 * 24 * 30, (int) config('sanctum.expiration'));
    }

    public function test_les_jetons_expires_sont_purges_chaque_nuit(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

        $this->assertStringContainsString('sanctum:prune-expired --hours=24', $commands);
    }
}
