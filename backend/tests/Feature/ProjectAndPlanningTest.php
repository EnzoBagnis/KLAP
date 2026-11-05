<?php

namespace Tests\Feature;

use App\Models\Funder;
use App\Models\Location;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests des fonctionnalités de l'Étape 3 : Projets, Planning et Lieux.
 */
class ProjectAndPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $artist;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => 1,
            'name' => 'Mariette Travard',
            'email' => 'mariette.travard@klap.demo',
            'password' => Hash::make('Klap2026!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->artist = User::create([
            'id' => 2,
            'name' => 'Marco Delaunay',
            'email' => 'marco.delaunay@klap.demo',
            'password' => Hash::make('Klap2026!'),
            'role' => 'contributor',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Grand Studio',
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_l_admin_peut_creer_un_projet_avec_lead_artist_et_financeurs(): void
    {
        $funder = Funder::create([
            'name' => 'Ville de Marseille',
            'type' => 'local_authority',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/projects', [
            'name' => 'Arts Danse 2026',
            'description' => 'Projet pilote au Lycée Saint-Charles',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'default_location_id' => $this->location->id,
            'lead_artist_id' => $this->artist->id,
            'funder_ids' => [$funder->id],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Projet créé avec succès.',
                'project' => [
                    'name' => 'Arts Danse 2026',
                ],
            ]);

        $this->assertDatabaseHas('projects', ['name' => 'Arts Danse 2026']);
        $this->assertDatabaseHas('project_user', [
            'user_id' => $this->artist->id,
            'member_role' => 'lead',
        ]);
    }

    public function test_un_artiste_ne_peut_pas_creer_de_projet(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/projects', [
            'name' => 'Projet Non Autorisé',
            'default_location_id' => $this->location->id,
            'lead_artist_id' => $this->artist->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_la_suppression_d_un_projet_exige_le_nom_exact(): void
    {
        $project = Project::create([
            'name' => 'Projet BRAISES 2026',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
        ]);

        // 1. Mauvais nom saisi -> rejet 422
        $wrongAttempt = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$project->id}", [
            'confirm_name' => 'Autre Nom Faux',
        ]);
        $wrongAttempt->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_name']);

        // 2. Bon nom saisi -> succès 200 et suppression réelle
        $goodAttempt = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$project->id}", [
            'confirm_name' => 'Projet BRAISES 2026',
        ]);
        $goodAttempt->assertStatus(200);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_gestion_des_lieux_avec_studios_klap_en_priorite(): void
    {
        Location::create([
            'name' => 'Collège Belle de Mai',
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => false,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->artist, 'sanctum')->getJson('/api/locations');

        $response->assertStatus(200);
        $data = $response->json();

        // Le premier lieu retourné doit être le Grand Studio (is_klap = true)
        $this->assertTrue((bool) $data[0]['is_klap']);
        $this->assertSame('Grand Studio', $data[0]['name']);
    }

    public function test_planification_d_une_seance_d_atelier(): void
    {
        $project = Project::create([
            'name' => 'Projet Test Planning',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
        ]);

        $subProject = SubProject::create([
            'project_id' => $project->id,
            'name' => 'Classe Seconde 3',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/planning', [
            'project_id' => $project->id,
            'sub_project_ids' => [$subProject->id],
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => '2026-11-10',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Séance planifiée avec succès.']);

        $this->assertDatabaseHas('scheduled_sessions', [
            'project_id' => $project->id,
            'referent_id' => $this->artist->id,
            'session_date' => '2026-11-10',
        ]);
    }

    public function test_la_creation_de_projet_normalise_les_espaces_multiples(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/projects', [
            'name' => "Projet    Espaces    Multiples    2026",
            'default_location_id' => $this->location->id,
            'lead_artist_id' => $this->artist->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('projects', [
            'name' => 'Projet Espaces Multiples 2026',
        ]);
    }

    public function test_la_suppression_alerte_sur_la_casse_et_tolere_les_espaces_internes(): void
    {
        $project = Project::create([
            'name' => 'Projet Sensibilisation Sud',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
        ]);

        // 1. Même texte mais mauvaise casse -> Alerte explicite sur les majuscules/minuscules
        $caseMismatch = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$project->id}", [
            'confirm_name' => 'projet sensibilisation sud',
        ]);
        $caseMismatch->assertStatus(422);
        $this->assertStringContainsString('casse (majuscules/minuscules)', $caseMismatch->json('errors.confirm_name.0'));

        // 2. Bon nom avec espaces doubles internes -> Accepté et supprimé
        $doubleSpaces = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$project->id}", [
            'confirm_name' => 'Projet   Sensibilisation   Sud',
        ]);
        $doubleSpaces->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_la_planification_rejette_une_heure_de_fin_invalide_ou_anterieure(): void
    {
        $project = Project::create([
            'name' => 'Projet Test Heures',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
        ]);

        // Heure de fin antérieure à l'heure de début
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/planning', [
            'project_id' => $project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => '2026-11-12',
            'start_time' => '16:00',
            'end_time' => '14:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
        $this->assertStringContainsString('postérieure', $response->json('errors.end_time.0'));
    }
}
