<?php

namespace Tests\Feature;

use App\Models\Funder;
use App\Models\Location;
use App\Models\LogbookEntry;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Grand test d'intégration de fin de saison (End-to-End).
 * Valide le cycle complet de l'application selon les exigences de Mariette Travard :
 * 1. Création de projet et sécurité de suppression (nom exact)
 * 2. Planning dynamique et pastille rouge « À remplir »
 * 3. Saisie terrain du carnet de bord (brouillon auto-sauvegardé puis validation finale 6 étapes)
 * 4. Verrouillage automatique J+10, demande de déverrouillage et validation admin avec grâce de 24h
 * 5. Exports des pré-bilans Word (.docx), Excel (.csv) et rejet formel du PDF
 */
class KlapApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $artist;
    private Location $studio;
    private Project $project;
    private SubProject $subProject;
    private ScheduledSession $pastSession;
    private ScheduledSession $futureSession;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Utilisateurs
        $this->admin = User::create([
            'name' => 'Mariette Travard',
            'email' => 'mariette.travard@klap.demo',
            'password' => Hash::make('Klap2026!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->artist = User::create([
            'name' => 'Léa Fournier',
            'email' => 'lea.fournier@klap.demo',
            'password' => Hash::make('Klap2026!'),
            'role' => 'contributor',
            'is_active' => true,
        ]);

        // 2. Lieu
        $this->studio = Location::create([
            'name' => 'Grand Studio KLAP',
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => true,
        ]);

        // 3. Financeur
        $funder = Funder::create([
            'name' => 'Ville de Marseille',
            'type' => 'local_authority',
        ]);

        // 4. Projet
        $this->project = Project::create([
            'name' => 'Arts Danse Collège 2026',
            'description' => 'Ateliers artistiques en collège',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
            'default_location_id' => $this->studio->id,
            'created_by' => $this->admin->id,
        ]);
        $this->project->users()->attach($this->artist->id, ['member_role' => 'lead']);
        $this->project->funders()->attach($funder->id);

        // 5. Sous-projet
        $this->subProject = SubProject::create([
            'project_id' => $this->project->id,
            'name' => 'Classe de 4ème B',
            'organization' => 'Collège Belle de Mai',
            'class_group' => '4ème B',
            'age_range' => '13-14 ans',
            'expected_headcount' => 25,
            'is_active' => true,
        ]);

        // 6. Séance passée (il y a 15 jours -> J+10 dépassé)
        $this->pastSession = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->studio->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
            'is_transversal' => false,
            'created_by' => $this->admin->id,
        ]);
        $this->pastSession->subProjects()->attach($this->subProject->id, ['project_id' => $this->project->id]);

        // 7. Séance future
        $this->futureSession = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->studio->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_type' => 'practice_workshop',
            'is_transversal' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_liste_des_projets_accessible(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Arts Danse Collège 2026']);
    }

    public function test_creation_de_projet_reussie_pour_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/projects', [
            'name' => 'Projet Danse Flamenco',
            'description' => 'Découverte du Flamenco',
            'start_date' => '2026-10-01',
            'end_date' => '2027-05-30',
            'default_location_id' => $this->studio->id,
            'lead_artist_id' => $this->artist->id,
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Projet Danse Flamenco']);
    }

    public function test_suppression_projet_echoue_si_le_nom_est_incorrect(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$this->project->id}", [
            'confirm_name' => 'Mauvais Nom',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_name']);
    }

    public function test_suppression_projet_echoue_pour_un_artiste(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->deleteJson("/api/projects/{$this->project->id}", [
            'confirm_name' => 'Arts Danse Collège 2026',
        ]);

        $response->assertStatus(403);
    }

    public function test_suppression_projet_reussie_pour_admin_avec_nom_exact(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/projects/{$this->project->id}", [
            'confirm_name' => 'Arts Danse Collège 2026',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
    }

    public function test_planning_retourne_statut_a_remplir_pour_seance_passee_non_remplie(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->getJson('/api/planning');

        $response->assertStatus(200);

        $sessions = $response->json();
        $pastSessionData = collect($sessions)->firstWhere('id', $this->pastSession->id);

        $this->assertNotNull($pastSessionData);
        $this->assertSame('À remplir', $pastSessionData['status']);
        $this->assertSame('urgent_missing', $pastSessionData['status_badge']);
    }

    public function test_intervenant_peut_sauvegarder_un_brouillon_auto_save(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/draft', [
            'scheduled_session_id' => $this->pastSession->id,
            'attendees_count' => 20,
            'what_works' => 'Première prise de contact très positive',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Brouillon sauvegardé avec succès.']);

        $this->assertDatabaseHas('logbook_entries', [
            'scheduled_session_id' => $this->pastSession->id,
            'status' => 'draft',
            'attendees_count' => 20,
        ]);
    }

    public function test_intervenant_peut_valider_une_fiche_complete(): void
    {
        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/submit', [
            'scheduled_session_id' => $this->futureSession->id,
            'attendees_count' => 22,
            'attendees_male' => 10,
            'attendees_female' => 12,
            'class_or_age' => '4ème (13-14 ans)',
            'pedagogical_goals' => 'Travail du rythme',
            'artistic_goals' => 'Exploration gestuelle',
            'adjustments_made' => true,
            'adjustments_reason' => 'Groupe très dynamique nécessitant un recentrage au sol',
            'exercises' => [
                ['order' => 1, 'name' => 'Échauffement articulaire', 'duration_minutes' => 15],
                ['order' => 2, 'name' => 'Traversées en duo', 'duration_minutes' => 30],
            ],
            'evaluations' => [
                ['engagement_score' => 4, 'expression_score' => 5, 'autonomy_score' => 3, 'progression_trend' => 'progres'],
            ],
            'what_works' => 'Excellente implication dans les duos',
            'next_session_notes' => 'Poursuivre la mémorisation de la séquence 2',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'submitted']);

        $this->assertDatabaseHas('logbook_entries', [
            'scheduled_session_id' => $this->futureSession->id,
            'status' => 'submitted',
            'attendees_count' => 22,
        ]);
    }

    public function test_fiche_verrouillee_a_j_plus_10_bloque_la_modification(): void
    {
        $entry = LogbookEntry::create([
            'scheduled_session_id' => $this->pastSession->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::now()->subDays(15),
            'edit_deadline' => Carbon::parse($this->pastSession->session_date)->addDays(10),
            'adjustment_done' => false,
            'attendees_count' => 20,
            'what_works' => 'Séance initiale',
            'next_steps' => 'Rien',
        ]);

        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/submit', [
            'scheduled_session_id' => $this->pastSession->id,
            'attendees_count' => 25,
            'adjustments_made' => false,
            'what_works' => 'Modification tardive',
            'next_session_notes' => 'Suite',
        ]);

        $response->assertStatus(403);
    }

    public function test_demande_et_validation_de_deverrouillage(): void
    {
        $entry = LogbookEntry::create([
            'scheduled_session_id' => $this->pastSession->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::now()->subDays(15),
            'edit_deadline' => Carbon::parse($this->pastSession->session_date)->addDays(10),
            'adjustment_done' => false,
            'attendees_count' => 20,
            'what_works' => 'Séance initiale',
            'next_steps' => 'Rien',
        ]);

        // 1. L'artiste demande le déverrouillage motivé
        $reqResponse = $this->actingAs($this->artist, 'sanctum')->postJson("/api/logbook/{$entry->id}/request-unlock", [
            'reason' => 'Oubli de saisie des effectifs filles/garçons après le spectacle.',
        ]);

        $reqResponse->assertStatus(200);
        $this->assertDatabaseHas('klap_notifications', ['type' => 'unlock_request']);

        // 2. Mariette valide le déverrouillage
        $unlockResponse = $this->actingAs($this->admin, 'sanctum')->postJson("/api/logbook/{$entry->id}/unlock");

        $unlockResponse->assertStatus(200);

        // 3. L'artiste peut maintenant modifier la fiche déverrouillée
        $entry->refresh();
        $this->assertNotNull($entry->unlocked_at);
        $this->assertFalse($entry->isLocked());
    }

    public function test_export_word_docx_fonctionne(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->get("/api/exports/projects/{$this->project->id}/word");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_export_csv_excel_fonctionne(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->get("/api/exports/projects/{$this->project->id}/csv");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_pdf_est_strictement_rejete_selon_exigence_client(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->getJson("/api/exports/projects/{$this->project->id}/pdf");

        $response->assertStatus(400)
            ->assertJsonFragment(['error' => 'Format non autorisé']);
    }
}
