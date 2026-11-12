<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Logbook\StoreLogbookEntryRequest;
use App\Http\Requests\Logbook\SubmitLogbookEntryRequest;
use App\Http\Requests\Logbook\UpdateLogbookEntryRequest;
use App\Http\Resources\LogbookEntryResource;
use App\Models\KlapNotification;
use App\Models\LogbookEntry;
use App\Models\ScheduledSession;
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

/**
 * Fiches de séance (#15, #19) : brouillon, soumission, lecture, réouverture. C'est le cœur
 * du carnet de bord.
 */
class LogbookEntryController extends Controller
{
    /** Les relations qu'on charge avec une fiche pour la renvoyer complète. */
    private const RELATIONS = ['author', 'unlockedBy', 'evaluation', 'exercises', 'scheduledSession.project', 'scheduledSession.location', 'scheduledSession.subProjects'];

    /**
     * Les fiches que l'utilisateur peut lire (droits emboîtés, #141) : voir
     * LogbookEntry::scopeReadableBy.
     *
     * @return Builder<LogbookEntry>
     */
    private function readable(User $user): Builder
    {
        return LogbookEntry::query()->readableBy($user);
    }

    /**
     * On liste les fiches lisibles, de la séance la plus récente à la plus ancienne, avec un
     * filtre facultatif sur le statut.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['sometimes', Rule::in(['draft', 'submitted', 'locked'])]],
            ['status.in' => 'Le statut demandé n’existe pas.']);

        $entries = $this->readable($request->user())
            // On qualifie les colonnes : la jointure avec les séances, qui ont aussi un statut,
            // les rendrait ambiguës.
            ->when($request->query('status'), fn (Builder $query, $status) => $query->where('logbook_entries.status', $status))
            ->with(self::RELATIONS)
            ->join('scheduled_sessions', 'scheduled_sessions.id', '=', 'logbook_entries.scheduled_session_id')
            ->orderByDesc('scheduled_sessions.session_date')
            ->select('logbook_entries.*')
            // On l'appelle après select() : sinon la sous-requête est effacée et chaque fiche
            // relit ses demandes de réouverture.
            ->withMax('unlockRequests', 'created_at')
            ->get();

        return LogbookEntryResource::collection($entries);
    }

    /**
     * On renvoie une fiche si l'utilisateur peut la lire ; sinon 404, comme pour une fiche
     * qui n'existe pas.
     *
     * @param  string  $entry  l'identifiant de la fiche, tel qu'il arrive dans l'URL
     */
    public function show(Request $request, string $entry): LogbookEntryResource
    {
        $found = ctype_digit($entry) ? $this->readable($request->user())->with(self::RELATIONS)->find($entry) : null;
        abort_if($found === null, 404);

        return new LogbookEntryResource($found);
    }

    /**
     * On renvoie la fiche d'une séance, pour l'ouvrir depuis le planning.
     *
     * @param  string  $session  l'identifiant de la séance, tel qu'il arrive dans l'URL
     */
    public function forSession(Request $request, string $session): LogbookEntryResource
    {
        $found = ctype_digit($session)
            ? $this->readable($request->user())->with(self::RELATIONS)->where('scheduled_session_id', $session)->first()
            : null;
        abort_if($found === null, 404);

        return new LogbookEntryResource($found);
    }

    /**
     * On ouvre la fiche d'une séance, en brouillon. La fiche appartient toujours à
     * l'intervenant·e référent·e, même si c'est l'administration qui l'ouvre.
     */
    public function store(StoreLogbookEntryRequest $request): JsonResponse
    {
        $session = $request->scheduledSession();

        try {
            $entry = DB::transaction(function () use ($request, $session) {
                // On relit la séance en la verrouillant : une annulation ou une modification
                // concurrente (#180) ne peut pas passer entre les contrôles de la requête et la
                // création de la fiche.
                $session = ScheduledSession::query()->lockForUpdate()->findOrFail($session->id);
                abort_if($session->status === 'cancelled', 409, 'Cette séance vient d’être annulée : elle n’a pas de fiche.');
                $entry = new LogbookEntry;
                $entry->scheduled_session_id = $session->id;
                // La fiche est celle de l'intervenant·e référent·e, même si l'administration
                // l'ouvre à sa place.
                $entry->author_id = $session->referent_id;
                $entry->status = 'draft';
                $entry->edit_deadline = $session->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS);
                $this->apply($entry, $request->validated());
                // On inscrit l'auteur comme artiste principal·e de la fiche (section 5).
                $entry->artists()->attach($session->referent_id, ['member_role' => 'lead']);

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // Si deux personnes ouvrent la fiche en même temps, la seconde bute sur l'index
            // unique de la séance.
            abort(409, 'Cette séance a déjà une fiche.');
        }

        return (new LogbookEntryResource($entry->fresh(self::RELATIONS)))->response()->setStatusCode(201);
    }

    /**
     * On enregistre un brouillon, ou on corrige une fiche déjà rendue.
     */
    public function update(UpdateLogbookEntryRequest $request): LogbookEntryResource
    {
        $entry = $request->entry();
        DB::transaction(fn () => $this->apply($entry, $request->payload()));

        return new LogbookEntryResource($entry->fresh(self::RELATIONS));
    }

    /**
     * On soumet la fiche : on vérifie qu'elle est complète, puis on prévient l'administration
     * à la première soumission.
     *
     * @throws ValidationException s'il manque des réponses obligatoires
     */
    public function submit(SubmitLogbookEntryRequest $request): LogbookEntryResource
    {
        $entry = $request->entry();
        $errors = LogbookRules::submissionErrors(LogbookRules::state($entry), $entry->scheduledSession);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $actor = $request->user();
        DB::transaction(function () use ($entry, $actor) {
            // On laisse la base décider s'il s'agit de la première soumission, plutôt que la
            // fiche chargée plus tôt : un double appui sur « Soumettre » n'envoie qu'une notification.
            $first = LogbookEntry::query()->whereKey($entry->id)->whereNull('submitted_at')
                ->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now()]) === 1;
            if (! $first) {
                LogbookEntry::query()->whereKey($entry->id)->update(['status' => 'submitted', 'updated_at' => now()]);
            }
            // Cahier des charges : « Notification à l'admin quand une fiche est remplie ». On
            // prévient aussi la super-utilisatrice (#84), une seule fois, à la première soumission.
            if ($first) {
                $this->notifySubmitted($entry, $actor);
            }
        });

        return new LogbookEntryResource($entry->fresh(self::RELATIONS));
    }

    /**
     * On rouvre une fiche verrouillée ou hors délai (administratrice, route `role:admin`) :
     * on donne une nouvelle échéance à J+10 du déverrouillage, et une fiche verrouillée
     * redevient soumise. On ne trace que le dernier déverrouillage (unlocked_by, unlocked_at).
     *
     * @param  string  $entry  l'identifiant de la fiche, tel qu'il arrive dans l'URL
     */
    public function unlock(Request $request, string $entry): LogbookEntryResource
    {
        abort_unless(ctype_digit($entry), 404);

        // On verrouille la ligne le temps de l'opération : la commande de verrouillage ne peut
        // pas passer la fiche en `locked` entre notre lecture et notre écriture.
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
            $found->edit_deadline = LogbookEntry::localNow()->addDays(LogbookEntry::EDIT_DAYS)->startOfDay();
            $found->unlocked_by = $request->user()->id;
            $found->unlocked_at = now();
            $found->save();

            return $found;
        });

        return new LogbookEntryResource($found->fresh(self::RELATIONS));
    }

    /**
     * L'auteur d'une fiche verrouillée demande sa réouverture (#130, repris de la branche de
     * Lucas, #121). On prévient les administratrices actives ; seule l'une d'elles rouvre
     * (contexte client). On n'accepte qu'une demande en attente, que la réouverture clôt.
     *
     * @param  string  $entry  l'identifiant de la fiche, tel qu'il arrive dans l'URL
     */
    public function requestUnlock(Request $request, string $entry): LogbookEntryResource
    {
        $user = $request->user();
        $found = ctype_digit($entry) ? $this->readable($user)->find($entry) : null;
        abort_if($found === null, 404);
        abort_unless($found->author_id === $user->id, 403, 'Seul·e l’auteur·rice de la fiche peut en demander la réouverture.');
        abort_if($user->hasRole(UserRole::Admin), 403, 'Vous pouvez rouvrir cette fiche vous-même.');

        DB::transaction(function () use ($found, $user) {
            // On verrouille la ligne : un double appui ne crée qu'une demande.
            $entry = LogbookEntry::query()->lockForUpdate()->with('scheduledSession.project')->findOrFail($found->id);
            abort_if($entry->scheduledSession->status === 'cancelled'
                || $entry->scheduledSession->project->status === 'archived', 403,
                'La séance a été annulée ou son projet archivé : la fiche ne se rouvre pas.');
            abort_unless($entry->isLocked(), 409, 'La fiche n’est pas verrouillée.');
            abort_if($entry->pendingUnlockRequestAt() !== null, 409,
                'Une demande de réouverture est déjà en attente pour cette fiche.');

            $sent = $this->notifyAdministration($entry, 'unlock_request', [UserRole::Admin], $user);
            abort_if($sent === 0, 503, 'Aucune administratrice ne peut recevoir la demande pour le moment.');
        });

        return new LogbookEntryResource($found->fresh(self::RELATIONS));
    }

    /**
     * On prévient l'administratrice et la super-utilisatrice qu'une fiche vient d'être rendue.
     */
    private function notifySubmitted(LogbookEntry $entry, User $actor): void
    {
        $this->notifyAdministration($entry, 'entry_submitted', [UserRole::Admin, UserRole::SuperUser], $actor);
    }

    /**
     * On notifie les comptes actifs des rôles donnés, sauf l'auteur de la fiche et la personne
     * qui agit (l'administration peut soumettre pour un·e intervenant·e, #141). Le message dit
     * qui agit, la date de la séance, le nom complet du projet et ses groupes, pour distinguer
     * les fiches d'un même jour.
     *
     * @param  string  $type  une clé de KlapNotification::MESSAGES
     * @param  list<UserRole>  $roles
     * @return int le nombre de notifications créées
     */
    private function notifyAdministration(LogbookEntry $entry, string $type, array $roles, User $actor): int
    {
        $message = KlapNotification::messageFor($type, $actor->name, $entry);

        $recipients = User::query()
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles))
            ->where('is_active', true)
            ->whereNotIn('id', [$entry->author_id, $actor->id])
            ->pluck('id');
        $recipients->each(fn (int $recipient) => KlapNotification::query()->create([
            'recipient_id' => $recipient,
            'type' => $type,
            'logbook_entry_id' => $entry->id,
            'message' => $message,
        ]));

        return $recipients->count();
    }

    /**
     * On écrit la saisie validée : colonnes, évaluation, exercices (qu'on remplace), présents.
     *
     * @param  array<string, mixed>  $data  la saisie, déjà validée par la requête
     */
    private function apply(LogbookEntry $entry, array $data): void
    {
        $entry->fill(array_intersect_key($data, array_flip(LogbookEntry::FIELDS)));
        // « Non fait » : on ne demande aucune explication (contexte client), et on efface l'ancienne.
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
        // Le lieu appartient à la séance : en le changeant dans la fiche, on le corrige pour
        // tout le monde (#215). La fiche renvoie son lieu à chaque enregistrement : on n'écrit
        // la séance que s'il a changé.
        if (array_key_exists('location_id', $data)) {
            ScheduledSession::query()->whereKey($entry->scheduled_session_id)
                ->where('location_id', '<>', $data['location_id'])
                ->update(['location_id' => $data['location_id']]);
        }
        foreach ($data['attendees'] ?? [] as $row) {
            DB::table('scheduled_session_sub_project')
                ->where('scheduled_session_id', $entry->scheduled_session_id)
                ->where('sub_project_id', $row['sub_project_id'])
                ->update(['attendees' => $row['count'] ?? null]);
        }
    }
}
