<?php

namespace Tests\Feature\Lists;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Création d'un lieu (#134, repris de la branche de Lucas, #121). */
class LocationStoreTest extends TestCase
{
    use RefreshDatabase;

    private function store(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->role(UserRole::Contributor)->create())
            ->postJson('/api/locations', [
                'name' => 'Théâtre du Merlan',
                'postal_code' => '13014',
                'city' => 'Marseille',
                ...$overrides,
            ]);
    }

    public static function roles(): array
    {
        return [
            'intervenant·e' => [UserRole::Contributor],
            'administratrice' => [UserRole::Admin],
            'super-utilisatrice' => [UserRole::SuperUser],
        ];
    }

    #[DataProvider('roles')]
    public function test_chacun_cree_un_lieu(UserRole $role): void
    {
        $user = User::factory()->role($role)->create();

        $response = $this->store(['address' => '  Avenue Raimu  '], $user)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Théâtre du Merlan')
            ->assertJsonPath('data.postal_code', '13014')
            ->assertJsonPath('data.city', 'Marseille');
        $this->assertSame(['id', 'name', 'postal_code', 'city'], array_keys($response->json('data')));

        $location = Location::query()->findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $location->created_by);
        $this->assertTrue($location->is_active);
        $this->assertFalse($location->is_klap);
        $this->assertSame('Avenue Raimu', $location->address);
    }

    #[DataProvider('roles')]
    public function test_personne_ne_se_declare_lieu_de_klap(UserRole $role): void
    {
        $id = $this->store(['is_klap' => true, 'is_active' => false, 'created_by' => 999], User::factory()->role($role)->create())
            ->assertCreated()
            ->json('data.id');

        $location = Location::query()->findOrFail($id);
        $this->assertFalse($location->is_klap);
        $this->assertTrue($location->is_active);
        $this->assertNotSame(999, $location->created_by);
    }

    public function test_le_lieu_cree_apparait_dans_la_liste(): void
    {
        $id = $this->store()->json('data.id');

        $this->getJson('/api/locations')->assertOk()->assertJsonFragment(['id' => $id, 'name' => 'Théâtre du Merlan']);
    }

    public function test_demande_une_connexion(): void
    {
        $this->postJson('/api/locations', [])->assertUnauthorized();
    }

    public static function invalid(): array
    {
        return [
            'sans nom' => [['name' => ''], 'name', 'Indiquez le nom du lieu.'],
            'sans code postal' => [['postal_code' => null], 'postal_code', 'Indiquez le code postal.'],
            'sans ville' => [['city' => '   '], 'city', 'Indiquez la ville.'],
            'code postal à 4 chiffres' => [['postal_code' => '1300'], 'postal_code', 'Le code postal doit compter 5 chiffres.'],
            'code postal à 6 chiffres' => [['postal_code' => '130030'], 'postal_code', 'Le code postal doit compter 5 chiffres.'],
            'code postal avec une lettre' => [['postal_code' => '1300A'], 'postal_code', 'Le code postal doit compter 5 chiffres.'],
            'nom trop long' => [['name' => str_repeat('a', 151)], 'name', 'Le nom ne doit pas dépasser 150 caractères.'],
            'ville trop longue' => [['city' => str_repeat('a', 101)], 'city', 'La ville ne doit pas dépasser 100 caractères.'],
            'adresse trop longue' => [['address' => str_repeat('a', 256)], 'address', 'L’adresse ne doit pas dépasser 255 caractères.'],
            'nom en tableau' => [['name' => ['a']], 'name', 'Le nom du lieu n’est pas valide.'],
            'code postal en nombre' => [['postal_code' => 13014], 'postal_code', 'Le code postal doit compter 5 chiffres.'],
            'code postal coupé par une espace' => [['postal_code' => '13 003'], 'postal_code', 'Le code postal doit compter 5 chiffres.'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_refuse_un_lieu_invalide(array $overrides, string $field, ?string $message): void
    {
        $response = $this->store($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
        if ($message) {
            $response->assertJsonPath("errors.{$field}.0", $message);
        }
        $this->assertSame(0, Location::query()->count());
    }

    public function test_refuse_un_doublon_meme_inactif_et_malgre_les_espaces(): void
    {
        Location::factory()->inactive()->create(['name' => 'Grand studio', 'postal_code' => '13003']);

        $this->store(['name' => '  Grand   studio ', 'postal_code' => '13003'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ce lieu existe déjà pour ce code postal.');
    }

    public function test_code_postal_entoure_d_espaces_et_adresse_vide(): void
    {
        $id = $this->store(['postal_code' => ' 13014 ', 'address' => '   '])->assertCreated()->json('data.id');

        $location = Location::query()->findOrFail($id);
        $this->assertSame('13014', $location->postal_code);
        $this->assertNull($location->address);
    }

    public function test_meme_nom_dans_une_autre_ville(): void
    {
        Location::factory()->create(['name' => 'Salle des fêtes', 'postal_code' => '13003']);

        $this->store(['name' => 'Salle des fêtes', 'postal_code' => '13100', 'city' => 'Aix-en-Provence'])->assertCreated();
    }

    public function test_doublon_qui_ne_differe_que_par_la_casse(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Comparaison insensible à la casse : collation MariaDB.');
        }
        Location::factory()->create(['name' => 'Grand studio', 'postal_code' => '13003']);

        $this->store(['name' => 'GRAND STUDIO', 'postal_code' => '13003'])->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
