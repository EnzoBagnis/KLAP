<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crée un utilisateur de test
        User::create([
            'id' => 1,
            'name' => 'Mariette Travard',
            'email' => 'mariette.travard@klap.demo',
            'password' => Hash::make('Klap2026!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_un_utilisateur_peut_se_connecter_avec_identifiants_valides(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'mariette.travard@klap.demo',
            'password' => 'Klap2026!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJson([
                'user' => [
                    'email' => 'mariette.travard@klap.demo',
                    'role' => 'admin',
                ],
            ]);
    }

    public function test_la_connexion_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'mariette.travard@klap.demo',
            'password' => 'MauvaisMotDePasse',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_l_utilisateur_authentifie_peut_consulter_son_profil_me(): void
    {
        $user = User::where('email', 'mariette.travard@klap.demo')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'Mariette Travard',
                    'role' => 'admin',
                ],
            ]);
    }

    public function test_l_utilisateur_peut_se_deconnecter(): void
    {
        $user = User::where('email', 'mariette.travard@klap.demo')->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Déconnexion réussie.']);
    }

    public function test_un_invite_peut_activer_son_compte_via_son_token(): void
    {
        $admin = User::first();
        $token = 'mon-token-secret-invitation';

        Invitation::create([
            'email' => 'nouvel.artiste@klap.demo',
            'full_name' => 'Nouvel Artiste',
            'role' => 'contributor',
            'token_hash' => hash('sha256', $token),
            'invited_by' => $admin->id,
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        // 1. Vérification du lien d'invitation
        $check = $this->getJson("/api/invitations/{$token}");
        $check->assertStatus(200)
            ->assertJson(['email' => 'nouvel.artiste@klap.demo']);

        // 2. Activation du compte
        $activate = $this->postJson("/api/invitations/{$token}/activate", [
            'name' => 'Artiste Activé',
            'password' => 'NouveauMotDePasse123!',
            'password_confirmation' => 'NouveauMotDePasse123!',
        ]);

        $activate->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'nouvel.artiste@klap.demo',
            'name' => 'Artiste Activé',
            'role' => 'contributor',
        ]);
    }
}
