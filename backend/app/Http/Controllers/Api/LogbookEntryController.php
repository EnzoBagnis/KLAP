<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Logbook\StoreLogbookEntryRequest;
use App\Http\Requests\Logbook\SubmitLogbookEntryRequest;
use App\Http\Requests\Logbook\UpdateLogbookEntryRequest;
use App\Http\Resources\LogbookEntryResource;
use App\Models\LogbookEntry;
use App\Models\User;
use App\Support\LogbookRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Fiches de séance (#15, #19) : brouillon, soumission, lecture. */
class LogbookEntryController extends Controller
{
    private const RELATIONS = ['author', 'unlockedBy', 'evaluation', 'exercises', 'scheduledSession.project', 'scheduledSession.location', 'scheduledSession.subProjects'];

    /** Fiches lisibles : toutes pour l'administration, les siennes pour un intervenant. */
    private function readable(User $user): Builder
    {
        return LogbookEntry::query()->when(
            ! $user->hasRole(UserRole::Admin, UserRole::SuperUser),
            fn (Builder $query) => $query->where('logbook_entries.author_id', $user->id),
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['sometimes', Rule::in(['draft', 'submitted', 'locked'])]],
            ['status.in' => 'Le statut demandé n’existe pas.']);

        $entries = $this->readable($request->user())
            // Colonnes qualifiées : la jointure avec les séances, qui ont aussi un statut, les rendrait ambiguës.
            ->when($request->query('status'), fn (Builder $query, $status) => $query->where('logbook_entries.status', $status))
            ->with(self::RELATIONS)
            ->join('scheduled_sessions', 'scheduled_sessions.id', '=', 'logbook_entries.scheduled_session_id')
            ->orderByDesc('scheduled_sessions.session_date')
            ->select('logbook_entries.*')
            ->get();

        return LogbookEntryResource::collection($entries);
    }

    /** Une fiche, si l'utilisateur peut la lire ; sinon 404 (comme une fiche inexistante). */
    public function show(Request $request, string $entry): LogbookEntryResource
    {
        $found = ctype_digit($entry) ? $this->readable($request->user())->with(self::RELATIONS)->find($entry) : null;
        abort_if($found === null, 404);

        return new LogbookEntryResource($found);
    }

    /** La fiche d'une séance, pour l'ouvrir depuis le planning. */
    public function forSession(Request $request, string $session): LogbookEntryResource
    {
        $found = ctype_digit($session)
            ? $this->readable($request->user())->with(self::RELATIONS)->where('scheduled_session_id', $session)->first()
            : null;
        abort_if($found === null, 404);

        return new LogbookEntryResource($found);
    }

    public function store(StoreLogbookEntryRequest $request): JsonResponse
    {
        $session = $request->scheduledSession();

        try {
            $entry = DB::transaction(function () use ($request, $session) {
                $entry = new LogbookEntry;
                $entry->scheduled_session_id = $session->id;
                $entry->author_id = $request->user()->id;
                $entry->status = 'draft';
                $entry->edit_deadline = $session->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS)->toDateString();
                $this->apply($entry, $request->validated());
                // L'auteur est l'artiste principal·e de la fiche (section 5).
                $entry->artists()->attach($request->user()->id, ['member_role' => 'lead']);

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux ouvertures simultanées : la seconde bute sur l'index unique de la séance.
            abort(409, 'Cette séance a déjà une fiche.');
        }

        return (new LogbookEntryResource($entry->fresh(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(UpdateLogbookEntryRequest $request): LogbookEntryResource
    {
        $entry = $request->entry();
        DB::transaction(fn () => $this->apply($entry, $request->payload()));

        return new LogbookEntryResource($entry->fresh(self::RELATIONS));
    }

    public function submit(SubmitLogbookEntryRequest $request): LogbookEntryResource
    {
        $entry = $request->entry();
        $errors = LogbookRules::submissionErrors(LogbookRules::state($entry), $entry->scheduledSession);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $entry->status = 'submitted';
        $entry->submitted_at ??= now();
        $entry->save();

        return new LogbookEntryResource($entry->fresh(self::RELATIONS));
    }

    /**
     * Rouvre une fiche verrouillée ou hors délai (administratrice, route `role:admin`) :
     * nouvelle échéance à J+10 du déverrouillage, une fiche verrouillée redevient soumise.
     * Seul le dernier déverrouillage est tracé (unlocked_by, unlocked_at).
     */
    public function unlock(Request $request, string $entry): LogbookEntryResource
    {
        abort_unless(ctype_digit($entry), 404);

        // Ligne verrouillée le temps de l'opération : la commande de verrouillage ne peut pas
        // passer la fiche en `locked` entre la lecture et l'écriture.
        $found = DB::transaction(function () use ($request, $entry) {
            $found = LogbookEntry::query()->lockForUpdate()->find($entry);
            abort_if($found === null, 404);
            $found->load('scheduledSession.project');
            abort_if($found->scheduledSession->status === 'cancelled'
                || $found->scheduledSession->project->status === 'archived', 403,
                'La séance a été annulée ou son projet archivé : la fiche ne se rouvre pas.');
            abort_unless($found->isLocked(), 409, 'La fiche n’est pas verrouillée.');

            // Une fiche rendue redevient soumise ; un brouillon reste un brouillon.
            $found->status = $found->status === 'draft' ? 'draft' : 'submitted';
            $found->edit_deadline = LogbookEntry::localNow()->addDays(LogbookEntry::EDIT_DAYS)->toDateString();
            $found->unlocked_by = $request->user()->id;
            $found->unlocked_at = now();
            $found->save();

            return $found;
        });

        return new LogbookEntryResource($found->fresh(self::RELATIONS));
    }

    /** Écrit la saisie validée : colonnes, évaluation, exercices (remplacés), présents. */
    private function apply(LogbookEntry $entry, array $data): void
    {
        $entry->fill(array_intersect_key($data, array_flip(LogbookEntry::FIELDS)));
        // « Non fait » : aucune explication n'est demandée (contexte client), l'ancienne est effacée.
        if ($entry->adjustment_done === false) {
            $entry->adjustment_detail = null;
        }
        $entry->save();

        if (array_key_exists('evaluation', $data)) {
            $entry->evaluation()->updateOrCreate([], $data['evaluation']);
        }
        if (array_key_exists('exercises', $data)) {
            $entry->exercises()->delete();
            foreach (array_values($data['exercises']) as $index => $label) {
                $entry->exercises()->create(['position' => $index + 1, 'label' => $label]);
            }
        }
        foreach ($data['attendees'] ?? [] as $row) {
            DB::table('scheduled_session_sub_project')
                ->where('scheduled_session_id', $entry->scheduled_session_id)
                ->where('sub_project_id', $row['sub_project_id'])
                ->update(['attendees' => $row['count'] ?? null]);
        }
    }
}
