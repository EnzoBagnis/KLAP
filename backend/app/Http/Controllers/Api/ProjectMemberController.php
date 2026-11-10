<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\LogbookEntry;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Équipe d'un projet (#251, anomalie AF7 de l'audit), administratrice seulement : ajouter un·e
 * intervenant·e ponctuel·le, ou le ou la retirer. Une séance ne peut être confiée qu'à un membre
 * du projet à sa date : sans cet écran, seul·e l'intervenant·e principal·e pouvait en animer.
 *
 * Retirer : refusé s'il reste des séances à venir à son nom (à confier à quelqu'un d'autre ou à
 * annuler d'abord). Avec des séances passées, l'appartenance se termine aujourd'hui : il ou elle
 * garde la lecture de ses fiches. Sinon, le lien est simplement supprimé.
 */
class ProjectMemberController extends Controller
{
    private const RELATIONS = ['defaultLocation', 'projectType', 'subProjects', 'members', 'funders'];

    public function store(Request $request, string $project): ProjectResource
    {
        $data = $request->validate([
            'user_id' => ['bail', 'required', 'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Contributor->value)->where('is_active', true)],
        ], [
            'user_id.required' => 'Choisissez l’intervenant·e à ajouter.',
            'user_id.integer' => 'Choisissez l’intervenant·e à ajouter.',
            'user_id.exists' => 'Seul un compte intervenant actif peut rejoindre l’équipe.',
        ]);

        $found = DB::transaction(function () use ($project, $data) {
            $found = $this->editable($project);
            $current = $found->members()->where('users.id', $data['user_id'])->first();
            if ($current && $current->pivot->end_date === null) {
                throw ValidationException::withMessages(['user_id' => 'Cette personne fait déjà partie de l’équipe.']);
            }
            if ($current) {
                // Revient dans l'équipe : sa date de fin est levée, son rôle ne change pas.
                $found->members()->updateExistingPivot($data['user_id'], ['end_date' => null]);
            } else {
                $found->members()->attach($data['user_id'], ['member_role' => 'occasional']);
            }

            return $found;
        });

        return new ProjectResource($found->fresh(self::RELATIONS));
    }

    public function destroy(string $project, string $user): ProjectResource
    {
        abort_unless(ctype_digit($user), 404);

        $found = DB::transaction(function () use ($project, $user) {
            $found = $this->editable($project);
            $member = $found->members()->where('users.id', $user)->first();
            abort_if($member === null || $member->pivot->end_date !== null, 404);
            abort_if($member->pivot->member_role === 'lead', 409,
                'C’est l’intervenant·e principal·e : choisissez-en un·e autre dans « Modifier » avant de la ou le retirer.');

            $today = LogbookEntry::localNow()->toDateString();
            $sessions = DB::table('scheduled_sessions')->where('project_id', $found->id)->where('referent_id', $member->id);
            $upcoming = (clone $sessions)->where('status', '<>', 'cancelled')->whereDate('session_date', '>=', $today)
                ->whereNotExists(fn ($q) => $q->from('logbook_entries')->whereColumn('logbook_entries.scheduled_session_id', 'scheduled_sessions.id'))
                ->count();
            if ($upcoming > 0) {
                $them = $upcoming > 1 ? 'les' : 'la';
                abort(409, sprintf('%s a encore %d séance%s à venir sur ce projet : confiez-%s à quelqu’un d’autre ou annulez-%s avant de la ou le retirer.',
                    $member->name, $upcoming, $upcoming > 1 ? 's' : '', $them, $them));
            }

            if ((clone $sessions)->exists()) {
                $found->members()->updateExistingPivot($member->id, ['end_date' => $today]);
            } else {
                $found->members()->detach($member->id);
            }

            return $found;
        });

        return new ProjectResource($found->fresh(self::RELATIONS));
    }

    /** Projet relu verrouillé ; archivé, il ne bouge plus. */
    private function editable(string $id): Project
    {
        abort_unless(ctype_digit($id), 404);
        $project = Project::query()->lockForUpdate()->find($id);
        abort_if($project === null, 404);
        abort_if($project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de modifier son équipe.');

        return $project;
    }
}
