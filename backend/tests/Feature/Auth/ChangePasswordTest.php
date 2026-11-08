<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Changer son mot de passe depuis son profil (#71), avec de vrais jetons Bearer. */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $lea;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lea = User::factory()->role(UserRole::Contributor)->create(['password' => 'Klap2026!', 'phone' => '06 12 34 56 78']);
        $this->token = $this->lea->createToken('ordinateur')->plainTextToken;
    }

    private function change(array $data, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->putJson('/api/me/password', $data + ['password_confirmation' => $data['password'] ?? null]);
    }

    public function test_change_le_mot_de_passe_et_deconnecte_les_autres_appareils(): void
    {
        $phone = $this->lea->createToken('téléphone')->plainTextToken;

        $this->change(['current_password' => 'Klap2026!', 'password' => 'Danse#2026'])
            ->assertOk()->assertJsonPath('message', 'Mot de passe changé. Vos autres appareils sont déconnectés.');

        $this->assertTrue(Hash::check('Danse#2026', $this->lea->fresh()->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson('/api/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_refuse_un_mot_de_passe_actuel_faux(): void
    {
        $this->change(['current_password' => 'Mauvais#1', 'password' => 'Danse#2026'])
            ->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'Le mot de passe actuel est incorrect.');
        $this->assertTrue(Hash::check('Klap2026!', $this->lea->fresh()->password));
    }

    public function test_applique_les_regles_et_refuse_le_meme_mot_de_passe(): void
    {
        $this->change(['current_password' => 'Klap2026!', 'password' => 'faible'])
            ->assertJsonPath('errors.password.0', 'Le mot de passe doit comporter au moins 8 caractères.');
        $this->change(['current_password' => 'Klap2026!', 'password' => 'Klap2026!'])
            ->assertJsonPath('errors.password.0', 'Le nouveau mot de passe doit être différent de l’actuel.');
        $this->change(['current_password' => 'Klap2026!', 'password' => 'Danse#2026', 'password_confirmation' => 'Autre#2026'])
            ->assertJsonPath('errors.password.0', 'La confirmation ne correspond pas au mot de passe.');
        $this->change(['password' => 'Danse#2026'])->assertJsonPath('errors.current_password.0', 'Saisissez votre mot de passe actuel.');
    }

    public function test_limite_les_essais(): void
    {
        foreach (range(1, 5) as $i) {
            $this->change(['current_password' => "Faux#{$i}aaa", 'password' => 'Danse#2026'])->assertUnprocessable();
        }

        $this->change(['current_password' => 'Klap2026!', 'password' => 'Danse#2026'])->assertTooManyRequests();
    }

    public function test_connexion_requise(): void
    {
        $this->putJson('/api/me/password', ['current_password' => 'Klap2026!', 'password' => 'Danse#2026'])->assertUnauthorized();
    }

    public function test_me_donne_le_telephone_et_la_derniere_connexion(): void
    {
        $this->postJson('/api/login', ['email' => $this->lea->email, 'password' => 'Klap2026!'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($this->token)->getJson('/api/me')
            ->assertJsonPath('user.phone', '06 12 34 56 78')
            ->assertJsonPath('user.last_login_at', $this->lea->fresh()->last_login_at->toIso8601String());
    }
}
