<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur de gestion du planning des ateliers et séances KLAP.
 * Gère le filtrage par mois, l'affichage des pastilles de statut (« À remplir » en rouge,
 * « Remplie », « Verrouillée »), les séances transversales, et l'ajout rapide de séances.
 */
class PlanningController extends Controller
{
    /**
     * Liste les séances planifiées pour un mois/année donné (ou tous par défaut).
     * Calcule le statut dynamique de chaque fiche de carnet de bord.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ScheduledSession::with([
            'project',
            'subProjects',
            'location',
            'referent',
            'logbookEntry',
        ]);

        // Filtrage par mois et année si fournis (ex: ?month=09&year=2026)
        if ($request->filled('month') && $request->filled('year')) {
            $month = (int) $request->input('month');
            $year = (int) $request->input('year');
            $query->forMonth($year, $month);
        }

        // Filtrage par projet si demandé
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        // Filtrage par sous-projet
        if ($request->filled('sub_project_id')) {
            $spId = $request->input('sub_project_id');
            $query->whereHas('subProjects', fn($q) => $q->where('sub_projects.id', $spId));
        }

        $sessions = $query->orderBy('session_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $today = Carbon::today();

        $formatted = $sessions->map(function (ScheduledSession $session) use ($user, $today) {
            $entry = $session->logbookEntry;
            $sessionDate = $session->session_date ? Carbon::parse($session->session_date) : null;
            $isPast = $sessionDate ? $sessionDate->lt($today) : false;

            // Calcul du statut de la fiche pour la pastille visuelle Figma
            if ($entry) {
                if ($entry->status === 'submitted') {
                    $isLocked = $entry->isLocked();
                    $status = $isLocked ? 'Verrouillée' : 'Remplie';
                    $statusBadge = $isLocked ? 'locked' : 'completed';
                } else {
                    $status = 'Brouillon en cours';
                    $statusBadge = 'draft';
                }
            } else {
                if ($isPast) {
                    // Séance passée sans fiche : Pastille ROUGE « À remplir » demandée par Mariette
                    $status = 'À remplir';
                    $statusBadge = 'urgent_missing';
                } else {
                    $status = 'À venir';
                    $statusBadge = 'upcoming';
                }
            }

            // Vérifie les droits de l'utilisateur connecté sur cette fiche
            $isAssigned = ($session->referent_id === $user->id);
            $canEdit = $user->isAdmin() || $user->isSuperUser() || ($isAssigned && (! $entry || ! $entry->isLocked()));
            $canRequestUnlock = ($entry && $entry->isLocked() && ($isAssigned || $user->isContributor()));

            return [
                'id' => $session->id,
                'session_date' => $session->session_date?->format('Y-m-d'),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'session_type' => $session->session_type,
                'is_transversal' => (bool) $session->is_transversal,
                'project' => [
                    'id' => $session->project->id,
                    'name' => $session->project->name,
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
                'status' => $status,
                'status_badge' => $statusBadge,
                'logbook_id' => $entry?->id,
                'can_edit' => $canEdit,
                'can_request_unlock' => $canRequestUnlock,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Ajoute une nouvelle séance planifiée (accessible depuis le planning ou le tableau de bord).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'sub_project_ids' => ['nullable', 'array'],
            'sub_project_ids.*' => ['integer', 'exists:sub_projects,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'referent_id' => ['nullable', 'integer', 'exists:users,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'session_type' => ['nullable', 'string', 'in:guided_tour,viewing_workshop,practice_workshop,complementary_workshop,performance,ongoing_project,other'],
            'session_type_detail' => ['nullable', 'string', 'max:100'],
            'is_transversal' => ['nullable', 'boolean'],
        ]);

        $session = DB::transaction(function () use ($validated, $request) {
            $session = ScheduledSession::create([
                'project_id' => $validated['project_id'],
                'location_id' => $validated['location_id'],
                'referent_id' => $validated['referent_id'] ?? $request->user()->id,
                'session_date' => $validated['session_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'session_type' => $validated['session_type'] ?? 'practice_workshop',
                'session_type_detail' => $validated['session_type_detail'] ?? null,
                'is_transversal' => $validated['is_transversal'] ?? false,
                'created_by' => $request->user()->id,
            ]);

            // Rattache les sous-projets si ce n'est pas une séance transversale globale
            if (! empty($validated['sub_project_ids'])) {
                $syncData = [];
                foreach ($validated['sub_project_ids'] as $spId) {
                    $syncData[$spId] = ['project_id' => $session->project_id];
                }
                $session->subProjects()->sync($syncData);
            }

            return $session;
        });

        return response()->json([
            'message' => 'Séance planifiée avec succès.',
            'session' => $session->load(['project', 'subProjects', 'location', 'referent']),
        ], 201);
    }
}
