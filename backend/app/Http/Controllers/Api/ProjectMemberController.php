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
 * Équipe d'un projet (#251, anomalie AF7 de notre audit), administratrice seulement : ajouter
 * un·e intervenant·e ponctuel·le, ou le ou la retirer. On ne peut confier une séance qu'à un
 * membre du projet à sa date : sans cet écran, seul·e l'intervenant·e principal·e pouvait en animer.
 *
 * Pour retirer quelqu'un : on refuse s'il lui reste des séances à venir (il faut d'abord les
 * confier à quelqu'un d'autre ou les annuler). S'il ou elle a des séances passées, on termine
 * son appartenance aujourd'hui, pour lui garder la lecture de ses fiches. Sinon, on supprime
 * simplement le lien.
 */
class ProjectMemberController extends Controller
{
    /** Les relations qu'on renvoie avec le projet, comme ProjectController. */
    private const RELATIONS = ['defaultLocation', 'projectType', 'subProjects', 'members', 'funders'];

    /**
     * On ajoute un·e intervenant·e ponctuel·le à l'équipe, ou on le ou la fait revenir.
     *
     * @param  string  $project  l'identifiant du projet, tel qu'il arrive dans l'URL
     *
     * @throws ValidationException si la personne fait déjà partie de l'équipe
     */
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
            if ($current && $current->pivot->getAttribute('end_date') === null) {
                throw ValidationException::withMessages(['user_id' => 'Cette personne fait déjà partie de l’équipe.']);
            }
            if ($current) {
                // La personne revient dans l'équipe : on ouvre une nouvelle période à partir
                // d'aujourd'hui, et on ne touche pas à son rôle.
                $found->members()->updateExistingPivot($data['user_id'], ['start_date' => LogbookEntry::localNow()->toDateString(), 'end_date' => null]);
            } else {
                $found->members()->attach($data['user_id'], ['member_role' => 'occasional']);
            }

            return $found;
        });

        return new ProjectResource($found->fresh(self::RELATIONS));
    }

    /**
     * On retire un·e intervenant·e ponctuel·le de l'équipe.
     *
     * @param  string  $project  l'identifiant du projet, tel qu'il arrive dans l'URL
     * @param  string  $user  l'identifiant du compte, tel qu'il arrive dans l'URL
     */
    public function destroy(string $project, string $user): ProjectResource
    {
        abort_unless(ctype_digit($user), 404);

        $found = DB::transaction(function () use ($project, $user) {
            $found = $this->editable($project);
            $member = $found->members()->where('users.id', $user)->first();
            abort_if($member === null || $member->pivot->getAttribute('end_date') !== null, 404);
            abort_if($member->pivot->getAttribute('member_role') === 'lead', 409,
                'C’est l’intervenant·e principal·e : choisissez-en un·e autre dans « Modifier » avant de la ou le retirer.');

            $now = LogbookEntry::localNow();
            $today = $now->toDateString();
            $sessions = DB::table('scheduled_sessions')->where('project_id', $found->id)->where('referent_id', $member->id)
                ->where('status', '<>', 'cancelled');
            // On compte comme « à venir » une séance d'un autre jour, ou d'aujourd'hui mais pas
            // encore terminée, et qui n'a pas encore de fiche.
            $upcoming = (clone $sessions)
                ->where(fn ($q) => $q->whereDate('session_date', '>', $today)
                    ->orWhere(fn ($q) => $q->whereDate('session_date', $today)->where('end_time', '>', $now->format('H:i:s'))))
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

    /**
     * On relit le projet en le verrouillant ; archivé, il ne bouge plus.
     *
     * @param  string  $id  l'identifiant du projet, tel qu'il arrive dans l'URL
     */
    private function editable(string $id): Project
    {
        abort_unless(ctype_digit($id), 404);
        $project = Project::query()->lockForUpdate()->find($id);
        abort_if($project === null, 404);
        abort_if($project->status === 'archived', 409, 'Ce projet est archivé : restaurez-le avant de modifier son équipe.');

        return $project;
    }
}
