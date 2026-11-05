<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\DeleteProjectRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\LogbookEntry;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur de gestion des projets de danse KLAP.
 * Gère le cycle de vie des projets, les intervenants (principal vs ponctuels),
 * les métriques agrégées (effectif MAX par classe/projet conforme à la règle client),
 * et la suppression hautement sécurisée avec confirmation obligatoire.
 */
class ProjectController extends Controller
{
    /**
     * Liste tous les projets avec leurs métriques agrégées et financeurs.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::with(['leadArtist', 'funders', 'subProjects', 'defaultLocation'])
            ->withCount(['scheduledSessions', 'subProjects'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Récupération des effectifs dédoublonnés calculés dynamiquement par la vue SQL v_project_participants
        $metrics = DB::table('v_project_participants')
            ->pluck('total_participants_max', 'project_id');

        $formatted = $projects->map(function (Project $project) use ($metrics) {
            $lead = $project->leadArtist->first();

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'status' => $project->status,
                'default_location' => $project->defaultLocation ? [
                    'id' => $project->defaultLocation->id,
                    'name' => $project->defaultLocation->name,
                    'city' => $project->defaultLocation->city,
                ] : null,
                'lead_artist' => $lead ? [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                ] : null,
                'funders' => $project->funders->map(fn($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'type' => $f->type,
                ]),
                'sub_projects_count' => $project->sub_projects_count,
                'sessions_count' => $project->scheduled_sessions_count,
                // Règle d'or de Mariette : somme des effectifs MAX de chaque sous-projet
                'total_participants_max' => (int) ($metrics[$project->id] ?? 0),
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Affiche les détails complets d'un projet spécifique.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load([
            'leadArtist',
            'occasionalArtists',
            'funders',
            'subProjects',
            'defaultLocation',
            'scheduledSessions.location',
            'scheduledSessions.subProjects',
            'scheduledSessions.referent',
            'scheduledSessions.logbookEntry',
        ]);

        $lead = $project->leadArtist->first();

        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'start_date' => $project->start_date?->format('Y-m-d'),
            'end_date' => $project->end_date?->format('Y-m-d'),
            'status' => $project->status,
            'default_location' => $project->defaultLocation ? [
                'id' => $project->defaultLocation->id,
                'name' => $project->defaultLocation->name,
                'city' => $project->defaultLocation->city,
            ] : null,
            'lead_artist' => $lead ? [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
            ] : null,
            // Intervenants ponctuels (alimentés automatiquement par les fiches)
            'occasional_artists' => $project->occasionalArtists->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
            ]),
            'funders' => $project->funders->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => $f->type,
            ]),
            'sub_projects' => $project->subProjects->map(function ($sp) {
                return [
                    'id' => $sp->id,
                    'name' => $sp->name,
                    'organization' => $sp->organization,
                    'class_group' => $sp->class_group,
                    'age_range' => $sp->age_range,
                    'expected_headcount' => $sp->expected_headcount,
                ];
            }),
            'scheduled_sessions' => $project->scheduledSessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'session_date' => $s->session_date?->format('Y-m-d'),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'is_transversal' => (bool) $s->is_transversal,
                    'location' => $s->location ? [
                        'id' => $s->location->id,
                        'name' => $s->location->name,
                        'city' => $s->location->city,
                    ] : null,
                    'artists' => $s->referent ? [['id' => $s->referent->id, 'name' => $s->referent->name]] : [],
                    'sub_projects' => $s->subProjects->map(fn($sp) => ['id' => $sp->id, 'name' => $sp->name]),
                    'has_logbook' => $s->logbookEntry !== null,
                    'logbook_status' => $s->logbookEntry ? $s->logbookEntry->status : 'missing',
                    'is_locked' => $s->logbookEntry ? $s->logbookEntry->isLocked() : false,
                ];
            }),
        ]);
    }

    /**
     * Enregistre un nouveau projet de danse.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $project = DB::transaction(function () use ($validated, $user) {
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'default_location_id' => $validated['default_location_id'],
                'created_by' => $user->id,
            ]);

            // Rattache l'artiste principal dans la table pivot avec le rôle 'lead'
            $project->users()->attach($validated['lead_artist_id'], ['member_role' => 'lead']);

            // Rattache les financeurs s'ils sont fournis
            if (! empty($validated['funder_ids'])) {
                $project->funders()->sync($validated['funder_ids']);
            }

            return $project;
        });

        return response()->json([
            'message' => 'Projet créé avec succès.',
            'project' => $project->load(['leadArtist', 'funders', 'defaultLocation']),
        ], 201);
    }

    /**
     * Met à jour un projet existant.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($project, $validated) {
            $project->update($validated);

            if (isset($validated['lead_artist_id'])) {
                // Met à jour l'artiste principal dans la pivot
                $project->users()->wherePivot('member_role', 'lead')->detach();
                $project->users()->syncWithoutDetaching([
                    $validated['lead_artist_id'] => ['member_role' => 'lead'],
                ]);
            }

            if (array_key_exists('funder_ids', $validated)) {
                $project->funders()->sync($validated['funder_ids'] ?? []);
            }
        });

        return response()->json([
            'message' => 'Projet mis à jour avec succès.',
            'project' => $project->fresh(['leadArtist', 'funders', 'subProjects', 'defaultLocation']),
        ]);
    }

    /**
     * Supprime définitivement un projet avec protection anti-erreur renforcée.
     * Conforme à la demande expresse de Mariette Travard :
     * Réservé à l'Administratrice + saisie obligatoire du nom exact du projet confirmée via DeleteProjectRequest.
     */
    public function destroy(DeleteProjectRequest $request, Project $project): JsonResponse
    {
        $projectName = $project->name;

        DB::transaction(function () use ($project) {
            // Nettoyage en cascade des données associées
            $sessionIds = $project->scheduledSessions()->pluck('id');
            $entryIds = LogbookEntry::whereIn('scheduled_session_id', $sessionIds)->pluck('id');

            DB::table('scheduled_session_sub_project')->where('project_id', $project->id)->delete();
            LogbookEntry::whereIn('id', $entryIds)->delete();
            $project->scheduledSessions()->delete();
            $project->subProjects()->delete();
            $project->customFields()->delete();
            $project->reportExports()->delete();
            $project->funders()->detach();
            $project->users()->detach();

            $project->delete();
        });

        return response()->json([
            'message' => "Le projet « {$projectName} » et toutes ses données associées ont été définitivement supprimés.",
        ]);
    }
}
