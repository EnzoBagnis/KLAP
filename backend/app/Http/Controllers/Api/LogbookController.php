<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logbook\DraftLogbookEntryRequest;
use App\Http\Requests\Logbook\RequestUnlockRequest;
use App\Http\Requests\Logbook\SubmitLogbookEntryRequest;
use App\Models\Exercise;
use App\Models\KlapNotification;
use App\Models\LogbookEntry;
use App\Models\ParticipantEvaluation;
use App\Models\ScheduledSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur de gestion des fiches du carnet de bord KLAP (Maison pour la danse).
 * Gère le formulaire 6 étapes :
 * 1. En-tête & Généralités (effectifs dédoublonnés, âges)
 * 2. Objectifs pédagogiques & artistiques
 * 3. Déroulé de la séance (exercices ordonnés 1 à 10, ajustements justifiés)
 * 4. Observations des participants (échelles 1 à 5, ressenti, progression)
 * 5. Bilan artiste (« Ce qui a fonctionné », notes max 1000 car)
 * 6. Perspectives & suites (« À reprendre », souhaits, maintien en classe)
 *
 * Implémente également l'auto-save (brouillon), le verrouillage J+10 et le déverrouillage motivé.
 */
class LogbookController extends Controller
{
    /**
     * Récupère la fiche de carnet de bord liée à une séance planifiée.
     * Si la fiche n'existe pas encore, renvoie les métadonnées de la séance pré-remplies,
     * ainsi que la continuité (objectifs/perspectives) de la séance précédente du même sous-projet.
     */
    public function showBySession(int $sessionId, Request $request): JsonResponse
    {
        $session = ScheduledSession::with([
            'project',
            'subProjects',
            'location',
            'referent',
            'logbookEntry.exercises',
            'logbookEntry.participantEvaluations',
            'logbookEntry.users',
        ])->findOrFail($sessionId);

        $entry = $session->logbookEntry;

        if ($entry) {
            $this->authorize('view', $entry);
        }

        // Continuité : recherche de la séance précédente du même groupe pour afficher les perspectives passées
        $previousNotes = null;
        $primarySubProject = $session->subProjects->first();

        if ($primarySubProject) {
            $previousSession = ScheduledSession::whereHas('subProjects', fn($q) => $q->where('sub_projects.id', $primarySubProject->id))
                ->where('session_date', '<', $session->session_date)
                ->whereHas('logbookEntry')
                ->orderBy('session_date', 'desc')
                ->first();

            if ($previousSession && $previousSession->logbookEntry) {
                $previousNotes = $previousSession->logbookEntry->next_session_notes;
            }
        }

        return response()->json([
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date?->format('Y-m-d'),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'is_transversal' => (bool) $session->is_transversal,
                'project' => [
                    'id' => $session->project->id,
                    'name' => $session->project->name,
                    'code' => $session->project->code,
                ],
                'sub_projects' => $session->subProjects->map(fn($sp) => [
                    'id' => $sp->id,
                    'name' => $sp->name,
                    'color_hex' => $sp->color_hex,
                ]),
                'location' => $session->location ? [
                    'id' => $session->location->id,
                    'name' => $session->location->name,
                    'structure' => $session->location->structure,
                    'city' => $session->location->city,
                    'postal_code' => $session->location->postal_code,
                    'is_klap' => (bool) $session->location->is_klap,
                ] : null,
                'referent' => $session->referent ? [
                    'id' => $session->referent->id,
                    'name' => $session->referent->name,
                ] : null,
            ],
            'previous_session_notes' => $previousNotes,
            'logbook' => $entry ? [
                'id' => $entry->id,
                'status' => $entry->status,
                'is_locked' => $entry->isLocked(),
                'unlock_reason' => $entry->unlock_reason,
                'unlock_requested_at' => $entry->unlock_requested_at?->format('Y-m-d H:i:s'),
                'unlocked_at' => $entry->unlocked_at?->format('Y-m-d H:i:s'),
                // Étape 1
                'attendees_count' => $entry->attendees_count,
                'attendees_male' => $entry->attendees_male,
                'attendees_female' => $entry->attendees_female,
                'class_or_age' => $entry->class_or_age,
                // Étape 2
                'pedagogical_goals' => $entry->pedagogical_goals,
                'artistic_goals' => $entry->artistic_goals,
                'competencies' => $entry->competencies,
                'progression' => $entry->progression,
                // Étape 3
                'warmup_desc' => $entry->warmup_desc,
                'closing' => $entry->closing,
                'feedback' => $entry->feedback,
                'adjustments_made' => (bool) $entry->adjustments_made,
                'adjustments_reason' => $entry->adjustments_reason,
                'exercises' => $entry->exercises->sortBy('order')->values()->map(fn($ex) => [
                    'id' => $ex->id,
                    'order' => $ex->order,
                    'name' => $ex->name,
                    'duration_minutes' => $ex->duration_minutes,
                    'description' => $ex->description,
                ]),
                // Étape 4
                'evaluations' => $entry->participantEvaluations->map(fn($ev) => [
                    'id' => $ev->id,
                    'engagement_score' => $ev->engagement_score,
                    'expression_score' => $ev->expression_score,
                    'autonomy_score' => $ev->autonomy_score,
                    'interactions_comment' => $ev->interactions_comment,
                    'feelings_choice' => $ev->feelings_choice,
                    'feelings_custom' => $ev->feelings_custom,
                    'progression_trend' => $ev->progression_trend,
                ]),
                // Étape 5
                'what_works' => $entry->what_works,
                'learnings' => $entry->learnings,
                'to_watch' => $entry->to_watch,
                'to_improve' => $entry->to_improve,
                'additional_notes' => $entry->additional_notes,
                // Étape 6
                'next_session_notes' => $entry->next_session_notes,
                'maintain_in_class' => (bool) $entry->maintain_in_class,
                'discarded_paths' => $entry->discarded_paths,
                'participant_wishes' => $entry->participant_wishes,
            ] : null,
        ]);
    }

    /**
     * Enregistre un brouillon partiel (Auto-save).
     * Permet la sauvegarde incrémentale sur smartphone sans exiger tous les champs.
     */
    public function saveDraft(DraftLogbookEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = (int) $request->input('scheduled_session_id');

        $session = ScheduledSession::with('project')->findOrFail($sessionId);

        $entry = LogbookEntry::firstOrNew(['scheduled_session_id' => $sessionId]);

        if ($entry->exists) {
            $this->authorize('update', $entry);
        }

        $savedEntry = DB::transaction(function () use ($entry, $session, $user, $request) {
            if (! $entry->exists) {
                $entry->author_id = $user->id;
            }

            $entry->status = 'draft';

            $data = $request->except(['exercises', 'evaluations', 'scheduled_session_id']);

            if ($request->has('adjustments_made')) {
                $data['adjustment_done'] = $request->boolean('adjustments_made');
            }
            if ($request->has('adjustments_reason')) {
                $data['adjustment_detail'] = $request->input('adjustments_reason');
            }
            if ($request->has('maintain_in_class')) {
                $data['keep_in_class'] = $request->boolean('maintain_in_class');
            }
            if ($request->has('next_session_notes')) {
                $data['next_steps'] = $request->input('next_session_notes');
            }
            if ($request->has('discarded_paths')) {
                $data['dropped_leads'] = $request->input('discarded_paths');
            }
            if ($request->has('pedagogical_goals') || $request->has('artistic_goals')) {
                $data['objectives'] = trim(($request->input('pedagogical_goals') ?? '') . ' / ' . ($request->input('artistic_goals') ?? ''), ' /');
            }

            if ($session->session_date) {
                $entry->edit_deadline = Carbon::parse($session->session_date)->addDays(10);
            }

            $entry->fill($data);
            $entry->save();

            // Enregistrement des exercices
            if ($request->has('exercises')) {
                $this->syncExercises($entry, $request->input('exercises', []));
            }

            // Enregistrement des observations / évaluations
            if ($request->has('evaluations')) {
                $this->syncEvaluations($entry, $request->input('evaluations', []));
            }

            // Association de l'intervenant ponctuel au projet selon la règle de Mariette Travard
            $this->autoAttachOccasionalArtist($session->project, $user);

            return $entry;
        });

        return response()->json([
            'message' => 'Brouillon sauvegardé automatiquement.',
            'logbook_id' => $savedEntry->id,
            'status' => 'draft',
        ]);
    }

    /**
     * Soumission finale et validation des 6 étapes de la fiche de séance.
     */
    public function submit(SubmitLogbookEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = (int) $request->input('scheduled_session_id');

        $session = ScheduledSession::with('project')->findOrFail($sessionId);

        $entry = LogbookEntry::firstOrNew(['scheduled_session_id' => $sessionId]);

        if ($entry->exists) {
            $this->authorize('update', $entry);
        }

        $submittedEntry = DB::transaction(function () use ($entry, $session, $user, $request) {
            if (! $entry->exists) {
                $entry->author_id = $user->id;
            }

            $entry->status = 'submitted';
            $entry->submitted_at = Carbon::now();

            $data = $request->except(['exercises', 'evaluations', 'scheduled_session_id']);

            if ($request->has('adjustments_made')) {
                $data['adjustment_done'] = $request->boolean('adjustments_made');
            }
            if ($request->has('adjustments_reason')) {
                $data['adjustment_detail'] = $request->input('adjustments_reason');
            }
            if ($request->has('maintain_in_class')) {
                $data['keep_in_class'] = $request->boolean('maintain_in_class');
            }
            if ($request->has('next_session_notes')) {
                $data['next_steps'] = $request->input('next_session_notes');
            }
            if ($request->has('discarded_paths')) {
                $data['dropped_leads'] = $request->input('discarded_paths');
            }
            if ($request->has('pedagogical_goals') || $request->has('artistic_goals')) {
                $data['objectives'] = trim(($request->input('pedagogical_goals') ?? '') . ' / ' . ($request->input('artistic_goals') ?? ''), ' /');
            }

            if ($session->session_date) {
                $entry->edit_deadline = Carbon::parse($session->session_date)->addDays(10);
            }

            $entry->fill($data);
            $entry->save();

            // Synchronisation stricte des exercices
            if ($request->has('exercises')) {
                $this->syncExercises($entry, $request->input('exercises', []));
            }

            // Synchronisation des évaluations
            if ($request->has('evaluations')) {
                $this->syncEvaluations($entry, $request->input('evaluations', []));
            }

            // Enregistrement de l'artiste ponctuel sur le projet
            $this->autoAttachOccasionalArtist($session->project, $user);

            return $entry;
        });

        return response()->json([
            'message' => 'Fiche carnet de bord validée et enregistrée avec succès.',
            'logbook_id' => $submittedEntry->id,
            'status' => 'submitted',
        ]);
    }

    /**
     * Formule une demande motivée de déverrouillage d'une fiche passée (J+10).
     */
    public function requestUnlock(RequestUnlockRequest $request, LogbookEntry $entry): JsonResponse
    {
        $this->authorize('requestUnlock', $entry);

        $user = $request->user();
        $reason = $request->input('reason');

        DB::transaction(function () use ($entry, $user, $reason) {
            $entry->update([
                'unlock_reason' => $reason,
                'unlock_requested_at' => Carbon::now(),
            ]);

            // Notification pour Mariette Travard et les administratrices
            $admins = User::where('role', 'admin')->get();
            $sessionDate = $entry->scheduledSession?->session_date?->format('d/m/Y') ?? 'Date non définie';

            foreach ($admins as $admin) {
                KlapNotification::create([
                    'recipient_id' => $admin->id,
                    'type' => 'unlock_request',
                    'logbook_entry_id' => $entry->id,
                    'message' => "L'intervenant {$user->name} demande la réouverture de la fiche du {$sessionDate}. Motif : {$reason}",
                ]);
            }
        });

        return response()->json([
            'message' => 'Votre demande de déverrouillage a été transmise à Mariette Travard.',
        ]);
    }

    /**
     * Déverrouille une fiche de séance (Action réservée à l'Administratrice).
     */
    public function unlock(Request $request, LogbookEntry $entry): JsonResponse
    {
        $this->authorize('unlock', $entry);

        DB::transaction(function () use ($entry) {
            $entry->update([
                'unlocked_at' => Carbon::now(),
            ]);

            // Notification pour l'auteur de la fiche
            if ($entry->author_id) {
                KlapNotification::create([
                    'recipient_id' => $entry->author_id,
                    'type' => 'unlock_approved',
                    'logbook_entry_id' => $entry->id,
                    'message' => "Mariette Travard a validé votre demande de déverrouillage. Vous disposez de la journée pour modifier votre fiche.",
                ]);
            }
        });

        return response()->json([
            'message' => 'La fiche a été déverrouillée avec succès pour 24 heures.',
        ]);
    }

    /**
     * Enregistre ou remplace la liste ordonnée des exercices (1 à 10).
     */
    private function syncExercises(LogbookEntry $entry, array $exercisesData): void
    {
        $entry->exercises()->delete();

        foreach ($exercisesData as $item) {
            $label = $item['name'] ?? $item['label'] ?? null;
            $pos = $item['order'] ?? $item['position'] ?? 1;
            if (! empty($label)) {
                Exercise::create([
                    'logbook_entry_id' => $entry->id,
                    'position' => $pos,
                    'label' => mb_substr($label, 0, 200),
                ]);
            }
        }
    }

    /**
     * Enregistre ou remplace les évaluations des participants.
     */
    private function syncEvaluations(LogbookEntry $entry, array $evaluationsData): void
    {
        $entry->participantEvaluations()->delete();

        $progressMap = [
            'recul' => 'regression',
            'constant' => 'stagnation',
            'progres' => 'improvement',
            'tres_fort_progres' => 'improvement',
            'first_session' => 'first_session',
        ];

        foreach ($evaluationsData as $item) {
            $trend = $item['progression_trend'] ?? $item['progress'] ?? null;
            $progress = $progressMap[$trend] ?? ($trend ?? null);

            ParticipantEvaluation::create([
                'logbook_entry_id' => $entry->id,
                'engagement' => $item['engagement_score'] ?? $item['engagement'] ?? null,
                'expression' => $item['expression_score'] ?? $item['expression'] ?? null,
                'autonomy' => $item['autonomy_score'] ?? $item['autonomy'] ?? null,
                'interaction' => $item['interactions_comment'] ?? $item['interaction'] ?? null,
                'feeling' => $item['feelings_choice'] ?? $item['feeling'] ?? null,
                'feeling_detail' => $item['feelings_custom'] ?? $item['feeling_detail'] ?? null,
                'progress' => $progress,
            ]);
            break; // 1 évaluation par fiche (contrainte unique sur logbook_entry_id)
        }
    }

    /**
     * Règle client : ajout automatique des artistes ponctuels au projet.
     */
    private function autoAttachOccasionalArtist($project, User $user): void
    {
        if (! $project) {
            return;
        }

        // Si l'utilisateur n'est pas déjà lié au projet (en lead ou ponctuel)
        $alreadyLinked = $project->users()->where('users.id', $user->id)->exists();

        if (! $alreadyLinked) {
            $project->users()->attach($user->id, ['member_role' => 'occasional']);
        }
    }
}
