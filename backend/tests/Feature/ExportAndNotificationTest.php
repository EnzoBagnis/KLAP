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

class ExportAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $artist;
    private Project $project;
    private Location $location;
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

        $this->location = Location::create([
            'name' => 'Studio 2',
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'name' => 'Projet Bilan Éclats 2026',
            'default_location_id' => $this->location->id,
            'created_by' => $this->admin->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
        ]);

        $this->project->users()->attach($this->artist->id, ['member_role' => 'lead']);

        $this->subProject = SubProject::create([
            'project_id' => $this->project->id,
            'name' => 'Groupe Danse et Santé',
            'organization' => 'Hôpital Européen',
        ]);

        $this->session = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => '2026-10-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);

        $this->session->subProjects()->attach($this->subProject->id, ['project_id' => $this->project->id]);

        LogbookEntry::create([
            'scheduled_session_id' => $this->session->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
            'attendees_count' => 18,
            'attendees_male' => 6,
            'attendees_female' => 12,
            'adjustment_done' => false,
            'what_works' => 'Énergie très communicative des soignants',
            'next_steps' => 'Poursuivre sur le contact improvisation',
            'additional_notes' => 'Remarquable implication du personnel',
        ]);
    }

    public function test_export_word_genere_un_docx_valide_avec_donnees_du_projet(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/exports/projects/{$this->project->id}/word");

        $response->assertStatus(200);

        // En-têtes HTTP conformes au format Word
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringContainsString('Pre-Bilan-projet-bilan-eclats-2026', $response->headers->get('Content-Disposition'));

        // Vérification de la signature binaire d'un fichier ZIP / OpenXML (.docx)
        $content = $response->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    public function test_export_excel_csv_genere_un_csv_utf8_avec_bom_et_points_virgules(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/exports/projects/{$this->project->id}/csv");

        $response->assertStatus(200);

        // En-têtes HTTP conformes
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Pre-Bilan-projet-bilan-eclats-2026', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();

        // Le fichier doit obligatoirement commencer par le BOM UTF-8 (\xEF\xBB\xBF)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Contenu du rapport CSV
        $this->assertStringContainsString('KLAP Maison pour la danse / Kelemenis & cie', $content);
        $this->assertStringContainsString('Projet Bilan Éclats 2026', $content);
        $this->assertStringContainsString('Énergie très communicative des soignants', $content);
    }

    public function test_tentative_export_pdf_est_strictement_rejetee_en_400_bad_request(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/exports/projects/{$this->project->id}/pdf");

        // Rejet 400 Bad Request demandé par Mariette Travard
        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Format non autorisé',
            ]);

        $this->assertStringContainsString('Mariette Travard', $response->json('message'));
        $this->assertStringContainsString('Word (.docx) ou Excel/CSV (.csv)', $response->json('message'));
    }

    public function test_gestion_des_notifications_et_marquage_comme_lu(): void
    {
        // 1. Crée 3 notifications pour l'admin
        $notif1 = KlapNotification::create([
            'recipient_id' => $this->admin->id,
            'type' => 'unlock_request',
            'message' => 'Demande de déverrouillage pour séance du 15/10',
        ]);

        $notif2 = KlapNotification::create([
            'recipient_id' => $this->admin->id,
            'type' => 'entry_submitted',
            'message' => 'Nouvelle fiche remplie par artiste',
        ]);

        $notif3 = KlapNotification::create([
            'recipient_id' => $this->admin->id,
            'type' => 'reminder',
            'message' => 'Rappel administratif',
        ]);

        // 2. Interroge la liste : unread_count doit valoir 3
        $listResponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/notifications');
        $listResponse->assertStatus(200);
        $this->assertSame(3, $listResponse->json('unread_count'));
        $this->assertCount(3, $listResponse->json('notifications'));

        // 3. Marque la première comme lue
        $readOne = $this->actingAs($this->admin, 'sanctum')->patchJson("/api/notifications/{$notif1->id}/read");
        $readOne->assertStatus(200);

        $notif1->refresh();
        $this->assertTrue($notif1->isRead());

        // Compteur doit maintenant valoir 2
        $listAfterOne = $this->actingAs($this->admin, 'sanctum')->getJson('/api/notifications');
        $this->assertSame(2, $listAfterOne->json('unread_count'));

        // 4. Marque toutes les restantes comme lues
        $readAll = $this->actingAs($this->admin, 'sanctum')->postJson('/api/notifications/mark-all-read');
        $readAll->assertStatus(200);

        // Compteur doit valoir 0
        $listFinal = $this->actingAs($this->admin, 'sanctum')->getJson('/api/notifications');
        $this->assertSame(0, $listFinal->json('unread_count'));
    }

    public function test_un_artiste_non_assigne_ne_peut_pas_exporter_le_projet_protection_idor(): void
    {
        $unassignedArtist = User::factory()->create([
            'role' => 'contributor',
            'is_active' => true,
        ]);

        // Tentative d'export Word -> 403 Forbidden
        $responseWord = $this->actingAs($unassignedArtist, 'sanctum')
            ->get("/api/exports/projects/{$this->project->id}/word");
        $responseWord->assertStatus(403);

        // Tentative d'export CSV -> 403 Forbidden
        $responseCsv = $this->actingAs($unassignedArtist, 'sanctum')
            ->get("/api/exports/projects/{$this->project->id}/csv");
        $responseCsv->assertStatus(403);
    }

    public function test_protection_anti_injection_de_formules_csv_owasp_cwe_1236(): void
    {
        // Séance avec tentative d'injection de formule tableur
        $sessionMalicious = ScheduledSession::create([
            'project_id' => $this->project->id,
            'location_id' => $this->location->id,
            'referent_id' => $this->artist->id,
            'session_date' => '2026-10-20',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'practice_workshop',
            'created_by' => $this->admin->id,
        ]);

        LogbookEntry::create([
            'scheduled_session_id' => $sessionMalicious->id,
            'author_id' => $this->artist->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
            'attendees_count' => 15,
            'adjustment_done' => false,
            'what_works' => '=SUM(A1:A10)', // Formule malveillante potentielle
            'next_steps' => '@DDE("calc")', // Commande DDE malveillante
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/exports/projects/{$this->project->id}/csv");

        $response->assertStatus(200);
        $content = $response->getContent();

        // Les formules doivent impérativement être neutralisées par une apostrophe
        $this->assertStringContainsString(";'=SUM(A1:A10);", $content);
        $this->assertStringNotContainsString(";=SUM(A1:A10);", $content);
    }
}
