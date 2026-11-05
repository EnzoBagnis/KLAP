<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Exercise;
use App\Models\Funder;
use App\Models\Invitation;
use App\Models\KlapNotification;
use App\Models\Location;
use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\ScheduledSession;
use App\Models\SubProject;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Test d'intégrité des 14 Modèles Eloquent de l'application KLAP.
 * Vérifie l'instanciation, les tables, les relations et la logique métier.
 */
class ModelIntegrityTest extends TestCase
{
    /**
     * Vérifie que les 14 modèles s'instancient sans erreur.
     */
    public function test_les_14_modeles_eloquent_sont_instanciables(): void
    {
        $models = [
            User::class,
            Location::class,
            Funder::class,
            Project::class,
            SubProject::class,
            ScheduledSession::class,
            LogbookEntry::class,
            Exercise::class,
            ParticipantEvaluation::class,
            CustomField::class,
            CustomFieldValue::class,
            Invitation::class,
            KlapNotification::class,
            ReportExport::class,
        ];

        foreach ($models as $class) {
            $instance = new $class();
            $this->assertInstanceOf($class, $instance, "Le modèle $class doit pouvoir être instancié.");
            $this->assertNotEmpty($instance->getTable(), "Le modèle $class doit être associé à une table SQL.");
        }
    }

    /**
     * Vérifie les rôles et helpers RBAC sur User.
     */
    public function test_helpers_rbac_sur_utilisateur(): void
    {
        $admin = new User(['role' => 'admin']);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperUser());
        $this->assertFalse($admin->isContributor());

        $mediator = new User(['role' => 'super_user']);
        $this->assertFalse($mediator->isAdmin());
        $this->assertTrue($mediator->isSuperUser());
        $this->assertFalse($mediator->isContributor());

        $artist = new User(['role' => 'contributor']);
        $this->assertFalse($artist->isAdmin());
        $this->assertFalse($artist->isSuperUser());
        $this->assertTrue($artist->isContributor());
    }

    /**
     * Vérifie le masquage des données sensibles (sécurité).
     */
    public function test_donnees_sensibles_masquees_sur_utilisateur(): void
    {
        $user = new User([
            'name' => 'Mariette Travard',
            'email' => 'mariette@kelemenis.fr',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array, 'Le mot de passe doit être masqué lors de la sérialisation JSON.');
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    /**
     * Vérifie la règle métier de verrouillage J+10 sur LogbookEntry.
     */
    public function test_regle_verrouillage_j_plus_10_logbook_entry(): void
    {
        // 1. Un brouillon n'est jamais verrouillé
        $draft = new LogbookEntry(['status' => 'draft']);
        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isLocked());

        // 2. Une fiche explicitement verrouillée
        $locked = new LogbookEntry(['status' => 'locked']);
        $this->assertTrue($locked->isLocked());

        // 3. Une fiche soumise dans la limite des 10 jours
        $sessionRecent = new ScheduledSession(['session_date' => Carbon::now()->subDays(3)->toDateString()]);
        $submittedRecent = new LogbookEntry([
            'status' => 'submitted',
            'edit_deadline' => Carbon::now()->addDays(7)->toDateString(),
        ]);
        $submittedRecent->setRelation('scheduledSession', $sessionRecent);
        $this->assertFalse($submittedRecent->isLocked(), 'Une fiche soumise avant J+10 reste modifiable.');

        // 4. Une fiche soumise après la limite de 10 jours
        $sessionOld = new ScheduledSession(['session_date' => Carbon::now()->subDays(15)->toDateString()]);
        $submittedOld = new LogbookEntry([
            'status' => 'submitted',
            'edit_deadline' => Carbon::now()->subDays(5)->toDateString(),
        ]);
        $submittedOld->setRelation('scheduledSession', $sessionOld);
        $this->assertTrue($submittedOld->isLocked(), 'Une fiche dont la date limite est dépassée doit être verrouillée.');

        // 5. Une fiche déverrouillée par l'administrateur aujourd'hui (fenêtre de grâce de 24h)
        $submittedOld->unlocked_at = Carbon::now();
        $this->assertFalse($submittedOld->isLocked(), 'Une fiche déverrouillée aujourd\'hui par l\'admin doit être temporairement modifiable.');
    }

    /**
     * Vérifie les helpers sur les invitations sécurisées.
     */
    public function test_helpers_invitations(): void
    {
        $pendingInvitation = new Invitation([
            'email' => 'artiste@test.fr',
            'expires_at' => Carbon::now()->addDays(5),
            'accepted_at' => null,
        ]);
        $this->assertFalse($pendingInvitation->isExpired());
        $this->assertFalse($pendingInvitation->isAccepted());

        $expiredInvitation = new Invitation([
            'email' => 'expire@test.fr',
            'expires_at' => Carbon::now()->subDay(),
            'accepted_at' => null,
        ]);
        $this->assertTrue($expiredInvitation->isExpired());

        $acceptedInvitation = new Invitation([
            'email' => 'active@test.fr',
            'expires_at' => Carbon::now()->addDays(2),
            'accepted_at' => Carbon::now(),
        ]);
        $this->assertTrue($acceptedInvitation->isAccepted());
    }

    /**
     * Vérifie la déclaration des relations Eloquent entre les modèles.
     */
    public function test_relations_eloquent_sont_definies(): void
    {
        $project = new Project();
        $this->assertNotNull($project->subProjects());
        $this->assertNotNull($project->scheduledSessions());
        $this->assertNotNull($project->funders());
        $this->assertNotNull($project->users());
        $this->assertNotNull($project->customFields());
        $this->assertNotNull($project->reportExports());

        $session = new ScheduledSession();
        $this->assertNotNull($session->project());
        $this->assertNotNull($session->location());
        $this->assertNotNull($session->referent());
        $this->assertNotNull($session->subProjects());
        $this->assertNotNull($session->logbookEntry());

        $logbook = new LogbookEntry();
        $this->assertNotNull($logbook->scheduledSession());
        $this->assertNotNull($logbook->author());
        $this->assertNotNull($logbook->exercises());
        $this->assertNotNull($logbook->participantEvaluation());
        $this->assertNotNull($logbook->attendingArtists());
        $this->assertNotNull($logbook->customFieldValues());
    }
}
