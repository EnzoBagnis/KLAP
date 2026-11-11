<?php

namespace Tests\Feature\Lists;

use App\Enums\UserRole;
use App\Models\Funder;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Listes des menus du formulaire projet (#92) : lieux, comptes, financeurs. */
class FormListsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create(['name' => 'Zoé Connectée']);
        Sanctum::actingAs($user);

        return $user;
    }

    public static function allRoutes(): array
    {
        return [
            'lieux' => ['/api/locations'],
            'comptes' => ['/api/users?role=contributor'],
            'financeurs' => ['/api/funders'],
        ];
    }

    #[DataProvider('allRoutes')]
    public function test_un_visiteur_non_connecte_est_refuse(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    public static function adminOnly(): array
    {
        return [
            'comptes, intervenant' => ['/api/users?role=contributor', UserRole::Contributor],
            'comptes, super-utilisatrice' => ['/api/users?role=contributor', UserRole::SuperUser],
            'financeurs, intervenant' => ['/api/funders', UserRole::Contributor],
            'financeurs, super-utilisatrice' => ['/api/funders', UserRole::SuperUser],
        ];
    }

    #[DataProvider('adminOnly')]
    public function test_comptes_et_financeurs_sont_reserves_a_l_admin(string $uri, UserRole $role): void
    {
        $this->actingAsRole($role);

        $this->getJson($uri)->assertForbidden();
    }

    public static function roles(): array
    {
        return array_map(fn (UserRole $role) => [$role], array_combine(
            array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
            UserRole::cases(),
        ));
    }

    #[DataProvider('roles')]
    public function test_tous_les_roles_lisent_les_lieux_actifs_tries_par_nom(UserRole $role): void
    {
        $this->actingAsRole($role);
        Location::factory()->create(['name' => 'Salle de création', 'postal_code' => '13003', 'city' => 'Marseille']);
        Location::factory()->create(['name' => 'Grand studio']);
        Location::factory()->inactive()->create(['name' => 'Ancienne salle']);

        $this->getJson('/api/locations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Grand studio')
            ->assertJsonPath('data.1', [
                'id' => Location::where('name', 'Salle de création')->value('id'),
                'name' => 'Salle de création',
                'postal_code' => '13003',
                'city' => 'Marseille',
            ]);
    }

    public function test_la_liste_des_intervenants_exclut_les_autres_roles_et_les_comptes_desactives(): void
    {
        $this->actingAsRole(UserRole::Admin);
        User::factory()->role(UserRole::Contributor)->create(['name' => 'Marco Delaunay']);
        User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);
        User::factory()->role(UserRole::Contributor)->create(['name' => 'Ancien Intervenant', 'is_active' => false]);
        User::factory()->role(UserRole::SuperUser)->create(['name' => 'Emilie Garetier']);

        $this->getJson('/api/users?role=contributor')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Léa Fournier')
            ->assertJsonPath('data.1.name', 'Marco Delaunay');
    }

    public function test_la_liste_des_comptes_ne_divulgue_rien_d_autre_que_l_identifiant_et_le_nom(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier']);

        $this->getJson('/api/users?role=contributor')
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $lea->id, 'name' => 'Léa Fournier']]]);
    }

    public function test_un_role_inconnu_est_refuse(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->getJson('/api/users?role=artiste')
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Le rôle demandé n’existe pas.');
    }

    public function test_le_role_est_obligatoire(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->getJson('/api/users')
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Précisez le rôle des comptes à lister.');
        $this->getJson('/api/users?role=')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    #[DataProvider('roles')]
    public function test_le_filtre_ne_renvoie_que_le_role_demande(UserRole $role): void
    {
        $this->actingAsRole(UserRole::Admin);
        foreach (UserRole::cases() as $other) {
            User::factory()->role($other)->create(['name' => 'Compte '.$other->value]);
        }

        $names = collect($this->getJson('/api/users?role='.$role->value)->assertOk()->json('data.*.name'));

        $this->assertContains('Compte '.$role->value, $names);
        foreach (UserRole::cases() as $other) {
            if ($other !== $role) {
                $this->assertNotContains('Compte '.$other->value, $names);
            }
        }
    }

    public function test_les_financeurs_sont_tous_listes_par_nom(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $ville = Funder::factory()->create(['name' => 'Ville de Marseille', 'type' => 'local_authority']);
        Funder::factory()->create(['name' => 'DRAC', 'type' => 'state']);

        $this->getJson('/api/funders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'DRAC')
            // Avec l'état et l'usage (#249), pour l'écran Gestion › Listes.
            ->assertJsonPath('data.1', ['id' => $ville->id, 'name' => 'Ville de Marseille', 'type' => 'local_authority', 'is_active' => true, 'projects_count' => 0]);
    }

    public function test_les_listes_vides_renvoient_un_tableau_vide(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->getJson('/api/locations')->assertExactJson(['data' => []]);
        $this->getJson('/api/funders')->assertExactJson(['data' => []]);
        $this->getJson('/api/users?role=contributor')->assertExactJson(['data' => []]);
    }

    public function test_les_valeurs_des_listes_sont_acceptees_a_la_creation_d_un_projet(): void
    {
        $this->actingAsRole(UserRole::Admin);
        Location::factory()->create();
        User::factory()->role(UserRole::Contributor)->create();
        Funder::factory()->create();

        $this->postJson('/api/projects', [
            'name' => 'Projet issu des menus',
            'default_location_id' => $this->getJson('/api/locations')->json('data.0.id'),
            'principal_user_id' => $this->getJson('/api/users?role=contributor')->json('data.0.id'),
            'funder_ids' => [$this->getJson('/api/funders')->json('data.0.id')],
        ])->assertCreated();
    }
}
