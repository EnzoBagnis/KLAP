<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Limite des tentatives de connexion (#150) : par compte et par IP, avec un plafond par IP.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On tente une connexion avec un mauvais mot de passe, depuis une adresse IP donnée.
     *
     * @return TestResponse<JsonResponse>
     */
    private function attempt(string $email, string $ip = '10.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $email, 'password' => 'mauvais']);
    }

    public function test_un_compte_est_freine_apres_cinq_essais(): void
    {
        foreach (range(1, 5) as $i) {
            $this->attempt('lea@klap.demo')->assertUnprocessable();
        }

        $this->attempt('lea@klap.demo')->assertTooManyRequests()->assertHeader('Retry-After');
        // On ne contourne pas la limite en changeant la casse ou en ajoutant des espaces.
        $this->attempt('  LEA@klap.demo ')->assertTooManyRequests();
    }

    public function test_un_collegue_sur_le_meme_reseau_se_connecte_encore(): void
    {
        foreach (range(1, 6) as $i) {
            $this->attempt('lea@klap.demo');
        }
        User::factory()->role(UserRole::Admin)->create(['email' => 'mariette@klap.demo', 'password' => 'Klap2026!']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/login', ['email' => 'mariette@klap.demo', 'password' => 'Klap2026!'])
            ->assertOk();
    }

    public function test_le_meme_compte_depuis_une_autre_ip_n_est_pas_bloque(): void
    {
        foreach (range(1, 6) as $i) {
            $this->attempt('lea@klap.demo');
        }

        $this->attempt('lea@klap.demo', '10.0.0.2')->assertUnprocessable();
    }

    public function test_plafond_par_ip(): void
    {
        foreach (range(1, 30) as $i) {
            $this->attempt("personne{$i}@klap.demo")->assertUnprocessable();
        }

        $this->attempt('encore@klap.demo')->assertTooManyRequests();
        $this->attempt('encore@klap.demo', '10.0.0.2')->assertUnprocessable();
    }
}
