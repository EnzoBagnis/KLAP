<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Léa Fournier',
            'email' => 'lea@klap.demo',
            'password' => 'Klap2026!',
            'role' => UserRole::Contributor,
        ], $attributes));
    }

    public function test_un_utilisateur_recoit_un_token_avec_les_bons_identifiants(): void
    {
        $this->user();

        $response = $this->postJson('/api/login', [
            'email' => 'lea@klap.demo',
            'password' => 'Klap2026!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'role_label']])
            ->assertJsonPath('user.role', 'contributor')
            ->assertJsonPath('user.role_label', 'Intervenant');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_le_mot_de_passe_n_est_jamais_renvoye(): void
    {
        $this->user();

        $response = $this->postJson('/api/login', [
            'email' => 'lea@klap.demo',
            'password' => 'Klap2026!',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $this->user();

        $this->postJson('/api/login', [
            'email' => 'lea@klap.demo',
            'password' => 'mauvais',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_une_adresse_inconnue_renvoie_le_meme_message(): void
    {
        $this->postJson('/api/login', [
            'email' => 'personne@klap.demo',
            'password' => 'Klap2026!',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', "L'adresse e-mail ou le mot de passe est incorrect.");
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $this->user(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => 'lea@klap.demo',
            'password' => 'Klap2026!',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', "Ce compte a été désactivé. Contactez l'administratrice.");
    }

    public function test_la_date_de_derniere_connexion_est_mise_a_jour(): void
    {
        $user = $this->user();
        $this->assertNull($user->last_login_at);

        $this->postJson('/api/login', [
            'email' => 'lea@klap.demo',
            'password' => 'Klap2026!',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_l_email_et_le_mot_de_passe_sont_obligatoires(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_renvoie_l_utilisateur_connecte(): void
    {
        $user = $this->user(['role' => UserRole::Admin, 'email' => 'mariette@klap.demo']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'mariette@klap.demo')
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_me_est_refuse_sans_token(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_la_deconnexion_revoque_le_token_utilise(): void
    {
        $user = $this->user();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // Le garde garde l'utilisateur en mémoire pour la durée du test,
        // on le réinitialise pour rejouer la requête comme un vrai client.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_la_deconnexion_ne_touche_pas_aux_autres_appareils(): void
    {
        $user = $this->user();
        $telephone = $user->createToken('telephone')->plainTextToken;
        $ordinateur = $user->createToken('ordinateur')->plainTextToken;

        $this->withToken($telephone)->postJson('/api/logout')->assertOk();

        $this->withToken($ordinateur)->getJson('/api/me')->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }
}
