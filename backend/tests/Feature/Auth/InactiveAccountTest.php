<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Un compte désactivé ne peut plus utiliser son jeton (#148). On envoie les requêtes avec
 * un vrai jeton Bearer.
 */
class InactiveAccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On appelle une route avec un jeton, comme un vrai client.
     *
     * @return TestResponse<JsonResponse>
     */
    private function withBearer(string $token, string $uri): TestResponse
    {
        // Dans un même test, le garde retient l'utilisateur d'une requête à l'autre : on le vide.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($uri);
    }

    public function test_le_jeton_d_un_compte_desactive_est_refuse(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();
        $token = $user->createToken('api')->plainTextToken;
        $this->withBearer($token, '/api/me')->assertOk();

        // On désactive directement en base, sans passer par l'application : le modèle n'est
        // pas prévenu, et le jeton doit être refusé quand même.
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->withBearer($token, '/api/me')->assertUnauthorized();
        $this->withBearer($token, '/api/projects')->assertUnauthorized();
        $this->withBearer($token, '/api/statistics')->assertUnauthorized();
    }

    public function test_desactiver_un_compte_supprime_ses_jetons_et_seulement_les_siens(): void
    {
        $user = User::factory()->role(UserRole::Contributor)->create();
        $other = User::factory()->role(UserRole::Contributor)->create();
        $user->createToken('téléphone');
        $user->createToken('ordinateur');
        $otherToken = $other->createToken('api')->plainTextToken;

        $user->update(['is_active' => false]);

        $this->assertSame(0, $user->tokens()->count());
        $this->withBearer($otherToken, '/api/me')->assertOk();
    }

    public function test_un_compte_reactive_doit_se_reconnecter(): void
    {
        $user = User::factory()->role(UserRole::Contributor)->create();
        $token = $user->createToken('api')->plainTextToken;

        $user->update(['is_active' => false]);
        $user->update(['is_active' => true]);

        $this->withBearer($token, '/api/me')->assertUnauthorized();
        $this->withBearer($user->createToken('api')->plainTextToken, '/api/me')->assertOk();
    }

    public function test_une_autre_modification_ne_touche_pas_aux_jetons(): void
    {
        $user = User::factory()->role(UserRole::Contributor)->create();
        $token = $user->createToken('api')->plainTextToken;

        $user->update(['name' => 'Léa Fournier']);

        $this->withBearer($token, '/api/me')->assertOk();
    }
}
