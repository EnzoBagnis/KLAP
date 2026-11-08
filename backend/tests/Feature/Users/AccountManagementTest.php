<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Gestion des comptes par l'administratrice (#159). */
class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Mariette Travard']);
    }

    private function toggle(User $target, bool $active, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin)->patchJson("/api/accounts/{$target->id}", ['is_active' => $active]);
    }

    public function test_liste_tous_les_comptes_les_actifs_d_abord(): void
    {
        User::factory()->role(UserRole::Contributor)->create(['name' => 'Ancien intervenant', 'is_active' => false]);
        User::factory()->role(UserRole::SuperUser)->create(['name' => 'Emilie Garetier', 'email' => 'emilie@klap.demo']);

        $this->actingAs($this->admin)->getJson('/api/accounts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Emilie Garetier')
            ->assertJsonPath('data.0.email', 'emilie@klap.demo')
            ->assertJsonPath('data.0.role', 'super_user')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.2.name', 'Ancien intervenant')
            ->assertJsonPath('data.2.is_active', false)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_desactiver_un_compte_revoque_ses_jetons_sans_rien_effacer(): void
    {
        $lea = User::factory()->role(UserRole::Contributor)->create();
        $lea->createToken('téléphone');

        $this->toggle($lea, false)->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $lea->tokens()->count());
        $this->assertDatabaseHas('users', ['id' => $lea->id, 'is_active' => false]);
    }

    public function test_reactiver_un_compte(): void
    {
        $lea = User::factory()->role(UserRole::Contributor)->create(['is_active' => false]);

        $this->toggle($lea, true)->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson('/api/login', ['email' => $lea->email, 'password' => 'password'])->assertOk();
    }

    public function test_on_ne_se_desactive_pas_soi_meme(): void
    {
        User::factory()->role(UserRole::Admin)->create();

        $this->toggle($this->admin, false)->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'Vous ne pouvez pas désactiver votre propre compte.');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_une_administratrice_en_desactive_une_autre(): void
    {
        $other = User::factory()->role(UserRole::Admin)->create();

        $this->toggle($other, false)->assertOk();
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_reserve_a_l_administratrice(): void
    {
        $lea = User::factory()->role(UserRole::Contributor)->create();
        foreach ([User::factory()->role(UserRole::SuperUser)->create(), $lea] as $user) {
            $this->actingAs($user)->getJson('/api/accounts')->assertForbidden();
            $this->toggle($lea, false, $user)->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/accounts')->assertUnauthorized();
    }

    public function test_saisies_invalides(): void
    {
        $lea = User::factory()->role(UserRole::Contributor)->create();

        $this->actingAs($this->admin)->patchJson("/api/accounts/{$lea->id}", [])->assertJsonValidationErrors('is_active');
        $this->actingAs($this->admin)->patchJson("/api/accounts/{$lea->id}", ['is_active' => 'peut-être'])->assertJsonValidationErrors('is_active');
        $this->actingAs($this->admin)->patchJson('/api/accounts/9999', ['is_active' => false])->assertNotFound();
        $this->actingAs($this->admin)->patchJson('/api/accounts/abc', ['is_active' => false])->assertNotFound();
    }
}
