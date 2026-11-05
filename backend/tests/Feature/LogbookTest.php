<?php

namespace Tests\Feature;

use App\Models\KlapNotification;
use App\Models\Location;
use App\Models\LogbookEntry;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogbookTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $artist;
    private User $secondArtist;
    private Location $location;
    private Project $project;
    private SubProject $subProject;
    private ScheduledSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->artist = User::factory()->create([
            'role' => 'contributor',
            'is_active' => true,
        ]);

        $this->secondArtist = User::factory()->create([
            'role' => 'contributor',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Grand Studio KLAP',
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'name' => 'Danse en Partage 2026',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
        ]);

        // Rattache l'artiste en lead
        $this->project->users()->attach($this->artist->id, ['member_role' => 'lead']);

        $this->subProject = SubProject::create([
            'project_id' => $this->project->id,
            'name' => 'Classe CM2 Belle de Mai',
        ]);

        $this->session = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::today()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);

        $this->session->subProjects()->attach($this->subProject->id, ['project_id' => $this->project->id]);
    }

    public function test_affichage_d_une_fiche_vide_avec_continuite_pedagogique(): void
    {
        // 1. Crée une première séance passée avec des notes de perspectives
        $pastSession = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::today()->subDays(7)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);
        $pastSession->subProjects()->attach($this->subProject->id, ['project_id' => $this->project->id]);

        LogbookEntry::create([
            'scheduled_session_id' => $pastSession->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
            'attendees_count' => 22,
            'attendees_male' => 10,
            'attendees_female' => 12,
            'adjustment_done' => false,
            'what_works' => 'Très bonne écoute collective',
            'next_steps' => 'Reprendre le travail au sol sur la respiration',
        ]);

        // 2. Interroge la nouvelle séance du même groupe
        $response = $this->actingAs($this->artist, 'sanctum')->getJson("/api/sessions/{$this->session->id}/logbook");

        $response->assertStatus(200);
        $this->assertSame('Reprendre le travail au sol sur la respiration', $response->json('previous_session_notes'));
        $this->assertNull($response->json('logbook'));
    }

    public function test_sauvegarde_d_un_brouillon_partiel_autosave(): void
    {
        $payload = [
            'scheduled_session_id' => $this->session->id,
            'attendees_count' => 24,
            'pedagogical_goals' => 'Travail du rythme et de la coordination',
            'what_works' => 'Bonne réactivité des élèves',
        ];

        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/draft', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Brouillon sauvegardé avec succès.']);

        $this->assertDatabaseHas('logbook_entries', [
            'scheduled_session_id' => $this->session->id,
            'status' => 'draft',
            'attendees_count' => 24,
            'author_id' => $this->artist->id,
        ]);
    }

    public function test_soumission_complete_des_6_etapes_et_evaluations(): void
    {
        $payload = [
            'scheduled_session_id' => $this->session->id,
            // Étape 1
            'attendees_count' => 25,
            'attendees_male' => 12,
            'attendees_female' => 13,
            'class_or_age' => 'CM2',
            // Étape 2
            'pedagogical_goals' => 'Sensibilisation à l espace scénique',
            'artistic_goals' => 'Exploration du mouvement ralenti',
            // Étape 3
            'warmup_desc' => 'Échauffement articulaire au sol',
            'adjustments_made' => true,
            'adjustments_reason' => 'Groupe très agité, recentrage nécessaire',
            'exercises' => [
                ['order' => 1, 'name' => 'Marche dans le silence'],
                ['order' => 2, 'name' => 'Statue vivante et relais'],
            ],
            // Étape 4
            'evaluations' => [
                [
                    'engagement_score' => 4,
                    'expression_score' => 5,
                    'autonomy_score' => 3,
                    'interactions_comment' => 'Entraide remarquable',
                    'progression_trend' => 'progres',
                ],
            ],
            // Étape 5
            'what_works' => 'L exercice des statues a captivé toute la classe',
            'learnings' => 'Meilleure gestion du silence',
            // Étape 6
            'next_session_notes' => 'Travailler le passage du sol à la verticalité',
            'maintain_in_class' => true,
        ];

        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/submit', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Fiche de carnet de bord enregistrée avec succès.']);

        $this->assertDatabaseHas('logbook_entries', [
            'scheduled_session_id' => $this->session->id,
            'status' => 'submitted',
            'attendees_count' => 25,
            'adjustment_done' => true,
            'adjustment_detail' => 'Groupe très agité, recentrage nécessaire',
            'keep_in_class' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'label' => 'Marche dans le silence',
            'position' => 1,
        ]);

        $this->assertDatabaseHas('participant_evaluations', [
            'engagement' => 4,
            'expression' => 5,
            'progress' => 'improvement',
        ]);
    }

    public function test_justification_obligatoire_si_ajustement_coche(): void
    {
        $payload = [
            'scheduled_session_id' => $this->session->id,
            'attendees_count' => 20,
            'adjustments_made' => true,
            // adjustments_reason manquant -> doit échouer en 422
            'what_works' => 'Rien de particulier',
            'next_session_notes' => 'À voir',
        ];

        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/submit', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['adjustments_reason']);
    }

    public function test_rattachement_automatique_d_un_artiste_ponctuel_au_projet(): void
    {
        // Un second artiste anime la séance
        $payload = [
            'scheduled_session_id' => $this->session->id,
            'attendees_count' => 18,
            'what_works' => 'Super co-animation',
        ];

        $response = $this->actingAs($this->secondArtist, 'sanctum')->postJson('/api/logbook/draft', $payload);
        $response->assertStatus(200);

        // Le second artiste doit avoir été rattaché automatiquement avec le rôle 'occasional'
        $this->assertDatabaseHas('project_user', [
            'project_id' => $this->project->id,
            'user_id' => $this->secondArtist->id,
            'member_role' => 'occasional',
        ]);
    }

    public function test_cycle_de_verrouillage_j_plus_10_et_demande_de_deverrouillage(): void
    {
        // 1. Fiche datant de plus de 10 jours
        $oldSession = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::today()->subDays(15)->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);

        $entry = LogbookEntry::create([
            'scheduled_session_id' => $oldSession->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::today()->subDays(15),
            'attendees_count' => 20,
            'attendees_male' => 10,
            'attendees_female' => 10,
            'adjustment_done' => false,
            'what_works' => 'Séance terminée',
            'next_steps' => 'Fin',
            'edit_deadline' => Carbon::today()->subDays(5),
        ]);

        // La fiche est verrouillée
        $this->assertTrue($entry->isLocked());

        // 2. L'artiste tente de modifier le brouillon -> Rejet 403 Forbidden
        $failAttempt = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/draft', [
            'scheduled_session_id' => $oldSession->id,
            'attendees_count' => 22,
        ]);
        $failAttempt->assertStatus(403);

        // 3. L'artiste demande le déverrouillage avec un motif valide
        $unlockRequest = $this->actingAs($this->artist, 'sanctum')->postJson("/api/logbook/{$entry->id}/request-unlock", [
            'reason' => 'Erreur de saisie dans le nombre de garçons et filles.',
        ]);
        $unlockRequest->assertStatus(200);

        // Mariette reçoit une notification
        $this->assertDatabaseHas('klap_notifications', [
            'recipient_id' => $this->admin->id,
            'type' => 'unlock_request',
            'logbook_entry_id' => $entry->id,
        ]);

        // 4. L'artiste ne peut pas déverrouiller lui-même -> 403 Forbidden
        $unauthorizedUnlock = $this->actingAs($this->artist, 'sanctum')->postJson("/api/logbook/{$entry->id}/unlock");
        $unauthorizedUnlock->assertStatus(403);

        // 5. Seule l'Administratrice valide le déverrouillage -> Succès 200
        $adminUnlock = $this->actingAs($this->admin, 'sanctum')->postJson("/api/logbook/{$entry->id}/unlock");
        $adminUnlock->assertStatus(200);

        // La fiche est déverrouillée (fenêtre de grâce de 24h) et l'administrateur signataire est tracé
        $entry->refresh();
        $this->assertFalse($entry->isLocked());
        $this->assertSame($this->admin->id, $entry->unlocked_by);

        // 6. L'artiste peut maintenant mettre à jour sa fiche
        $successUpdate = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/draft', [
            'scheduled_session_id' => $oldSession->id,
            'attendees_count' => 22,
        ]);
        $successUpdate->assertStatus(200);
    }

    public function test_la_soumission_rejette_une_somme_filles_garcons_superieure_a_l_effectif_total(): void
    {
        $payload = [
            'scheduled_session_id' => $this->session->id,
            'attendees_count' => 20,
            'attendees_male' => 15,
            'attendees_female' => 10, // 15 + 10 = 25 > 20 !
            'adjustments_made' => false,
            'what_works' => 'Bon atelier',
            'next_session_notes' => 'Suite de la séance',
        ];

        $response = $this->actingAs($this->artist, 'sanctum')->postJson('/api/logbook/submit', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendees_count']);
        $this->assertStringContainsString('ne peut pas dépasser', $response->json('errors.attendees_count.0'));
    }

    public function test_la_demande_de_deverrouillage_bloque_les_doublons_en_attente(): void
    {
        $oldSession = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => Carbon::today()->subDays(12)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);

        $entry = LogbookEntry::create([
            'scheduled_session_id' => $oldSession->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::today()->subDays(12),
            'attendees_count' => 15,
            'adjustment_done' => false,
            'what_works' => 'Séance 1',
            'next_steps' => 'Séance 2',
            'edit_deadline' => Carbon::today()->subDays(2),
        ]);

        // Première demande -> OK 200
        $firstAttempt = $this->actingAs($this->artist, 'sanctum')->postJson("/api/logbook/{$entry->id}/request-unlock", [
            'reason' => 'Oubli d enregistrer les exercices d atelier.',
        ]);
        $firstAttempt->assertStatus(200);

        // Deuxième demande immédiate (doublon anti-spam) -> Rejet 422
        $spamAttempt = $this->actingAs($this->artist, 'sanctum')->postJson("/api/logbook/{$entry->id}/request-unlock", [
            'reason' => 'Deuxième demande inutile en double.',
        ]);
        $spamAttempt->assertStatus(422)
            ->assertJson(['message' => 'Une demande de déverrouillage est déjà en cours de traitement par Mariette Travard.']);
    }
}
