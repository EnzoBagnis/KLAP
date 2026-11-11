<?php

namespace Tests\Feature\Lists;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Lieux corrigés, désactivés et réactivés par l'administratrice (#249). */
class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_administratrice_voit_tous_les_lieux_les_autres_seulement_les_actifs(): void
    {
        Location::factory()->create(['name' => 'Grand studio']);
        Location::factory()->inactive()->create(['name' => 'Salle fermée']);

        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->getJson('/api/locations?tous=1')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.is_active', false);
        $this->getJson('/api/locations')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());
        $this->getJson('/api/locations?tous=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_corrige_desactive_et_reactive_un_lieu(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $place = Location::factory()->create(['name' => 'Gymnase', 'postal_code' => '13003', 'city' => 'Marseile']);

        $this->patchJson("/api/locations/{$place->id}", ['city' => ' Marseille ', 'address' => '12 rue Vallès'])->assertOk()
            ->assertJsonPath('data.city', 'Marseille')
            ->assertJsonPath('data.address', '12 rue Vallès');
        $this->patchJson("/api/locations/{$place->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/locations')->assertJsonCount(0, 'data');
        $this->patchJson("/api/locations/{$place->id}", ['is_active' => true])->assertOk();
        $this->getJson('/api/locations')->assertJsonCount(1, 'data');
    }

    public function test_refuse_un_doublon_et_un_code_postal_invalide(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        Location::factory()->create(['name' => 'Grand studio', 'postal_code' => '13003']);
        $place = Location::factory()->create(['name' => 'Petit studio', 'postal_code' => '13003']);

        $this->patchJson("/api/locations/{$place->id}", ['name' => 'Grand studio'])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ce lieu existe déjà pour ce code postal.');
        $this->patchJson("/api/locations/{$place->id}", ['postal_code' => '1300'])->assertUnprocessable()
            ->assertJsonPath('errors.postal_code.0', 'Le code postal doit compter 5 chiffres.');
        $this->patchJson("/api/locations/{$place->id}", ['name' => 'Petit studio'])->assertOk();
    }

    public function test_reserve_a_l_administratrice(): void
    {
        $place = Location::factory()->create();
        Sanctum::actingAs(User::factory()->role(UserRole::Contributor)->create());
        $this->patchJson("/api/locations/{$place->id}", ['is_active' => false])->assertForbidden();
        Sanctum::actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->patchJson('/api/locations/9x', ['is_active' => false])->assertNotFound();
    }
}
