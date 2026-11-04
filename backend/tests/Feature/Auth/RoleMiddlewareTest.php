<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route de test : le middleware est vérifié ici, pas sur les routes
        // métier, qui n'existent pas encore (voir #55).
        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get('/api/test-admin', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'role:admin,super_user'])
            ->get('/api/test-lecture', fn () => response()->json(['ok' => true]));
    }

    private function tokenPour(UserRole $role): string
    {
        $user = User::create([
            'name' => 'Test '.$role->value,
            'email' => $role->value.'@klap.demo',
            'password' => 'Klap2026!',
            'role' => $role,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_l_administratrice_passe(): void
    {
        $this->withToken($this->tokenPour(UserRole::Admin))
            ->getJson('/api/test-admin')->assertOk();
    }

    public function test_un_intervenant_est_refuse(): void
    {
        $this->withToken($this->tokenPour(UserRole::Contributor))
            ->getJson('/api/test-admin')->assertStatus(403);
    }

    public function test_la_super_utilisatrice_est_refusee_sur_une_route_admin(): void
    {
        $this->withToken($this->tokenPour(UserRole::SuperUser))
            ->getJson('/api/test-admin')->assertStatus(403);
    }

    public function test_une_route_ouverte_a_deux_roles_accepte_les_deux(): void
    {
        $this->withToken($this->tokenPour(UserRole::Admin))
            ->getJson('/api/test-lecture')->assertOk();

        $this->withToken($this->tokenPour(UserRole::SuperUser))
            ->getJson('/api/test-lecture')->assertOk();
    }

    public function test_sans_token_la_route_renvoie_401(): void
    {
        $this->getJson('/api/test-admin')->assertStatus(401);
    }
}
