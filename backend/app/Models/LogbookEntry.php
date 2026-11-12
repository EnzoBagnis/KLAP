<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Fiche de séance (carnet de bord). Elle hérite de sa séance (date, lieu, groupes)
 * et suit le cycle brouillon, soumise, verrouillée (J+10).
 *
 * @property int $id
 * @property int $scheduled_session_id
 * @property int $author_id
 * @property string $status
 * @property string|null $objectives
 * @property bool|null $adjustment_done
 * @property string|null $adjustment_detail
 * @property string|null $highlights
 * @property string|null $tools_resources
 * @property string|null $learnings
 * @property string|null $artist_review
 * @property string|null $next_steps
 * @property string|null $dropped_leads
 * @property bool $keep_in_class
 * @property string|null $additional_notes
 * @property Carbon|null $edit_deadline
 * @property Carbon|null $submitted_at
 * @property int|null $unlocked_by
 * @property Carbon|null $unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledSession $scheduledSession
 * @property-read User $author
 * @property-read ParticipantEvaluation|null $evaluation
 * @property-read Collection<int, Exercise> $exercises
 * @property-read Collection<int, User> $artists
 * @property-read Collection<int, KlapNotification> $unlockRequests
 *
 * @method static Builder<static> readableBy(User $user)
 */
class LogbookEntry extends Model
{
    /** Les colonnes du socle de la fiche, que son auteur peut modifier. */
    public const FIELDS = [
        'objectives', 'adjustment_done', 'adjustment_detail', 'highlights', 'tools_resources',
        'learnings', 'artist_review', 'next_steps', 'dropped_leads', 'keep_in_class', 'additional_notes',
    ];

    /** Le délai de modification après la séance, en jours (contexte client : on verrouille à J+10). */
    public const EDIT_DAYS = 10;

    /** On compte les échéances à l'heure de KLAP, pas en UTC (l'application, elle, reste en UTC). */
    public const LOCAL_TIMEZONE = 'Europe/Paris';

    /**
     * L'instant présent à l'heure de KLAP. On passe par ici pour toute comparaison de date :
     * à 23 h 30 à Marseille, on est encore « aujourd'hui », alors qu'en UTC on a changé de jour.
     */
    public static function localNow(): Carbon
    {
        return now(self::LOCAL_TIMEZONE);
    }

    /**
     * La date de rattrapage (#269), si on en a réglé une sur le serveur (KLAP_CATCH_UP_UNTIL).
     * La cliente a demandé à pouvoir saisir, pendant le test, les fiches de séances déjà
     * anciennes : jusqu'à cette date incluse, le délai de 10 jours ne ferme aucune fiche.
     */
    public static function catchUpUntil(): ?Carbon
    {
        $date = config('app.catch_up_until');
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return Carbon::parse($date, self::LOCAL_TIMEZONE)->startOfDay();
    }

    /**
     * Le dernier jour où on peut ouvrir et modifier la fiche d'une séance : 10 jours après la
     * séance, ou la date de rattrapage si elle tombe plus tard.
     */
    public static function deadlineFor(Carbon $sessionDate): Carbon
    {
        $deadline = Carbon::parse($sessionDate->toDateString(), self::LOCAL_TIMEZONE)->addDays(self::EDIT_DAYS);
        $catchUp = self::catchUpUntil();

        return $catchUp !== null && $catchUp->gt($deadline) ? $catchUp : $deadline;
    }

    /** @var list<string> */
    protected $fillable = self::FIELDS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_done' => 'boolean',
            'keep_in_class' => 'boolean',
            'edit_deadline' => 'date',
            'submitted_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    /**
     * La séance que la fiche raconte.
     *
     * @return BelongsTo<ScheduledSession, $this>
     */
    public function scheduledSession(): BelongsTo
    {
        return $this->belongsTo(ScheduledSession::class);
    }

    /**
     * La personne qui remplit la fiche.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /*
     * Droits emboîtés du cahier des charges (#141) : chaque rôle a ceux du rôle en dessous.
     * - intervenant·e : écrit ses fiches, lit celles des projets où il ou elle intervient ;
     * - super-utilisatrice : lit et modifie toutes les fiches, crée la fiche d'une séance ;
     * - administratrice : la même chose (et rouvre les fiches verrouillées).
     */

    /**
     * On limite la requête aux fiches que l'utilisateur peut lire : toutes pour
     * l'administration ; pour un·e intervenant·e, les siennes et celles de ses projets.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::SuperUser)) {
            return $query;
        }

        if ($user->hasRole(UserRole::Contributor)) {
            return $query->where(fn (Builder $own) => $own
                ->where('logbook_entries.author_id', $user->id)
                ->orWhereHas('scheduledSession.project.members', fn (Builder $members) => Project::currentMember($members, $user)));
        }

        // Pour un rôle qu'on ajouterait plus tard, on ne montre rien, comme Project::scopeVisibleTo.
        return $query->whereRaw('1 = 0');
    }

    /**
     * On vérifie que l'utilisateur peut modifier la fiche, verrouillage mis à part : son
     * auteur, l'administratrice ou la super-utilisatrice.
     */
    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null
            && ($this->author_id === $user->id || $user->hasRole(UserRole::Admin, UserRole::SuperUser));
    }

    /**
     * L'administratrice qui a rouvert la fiche en dernier.
     *
     * @return BelongsTo<User, $this>
     */
    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    /**
     * On considère la fiche verrouillée si son statut le dit ou si le délai est dépassé :
     * un brouillon hors délai n'a jamais le statut `locked`, mais on ne peut plus l'écrire.
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->deadlinePassed();
    }

    /**
     * Les demandes de réouverture : on crée une notification unlock_request par administratrice prévenue.
     *
     * @return HasMany<KlapNotification, $this>
     */
    public function unlockRequests(): HasMany
    {
        return $this->hasMany(KlapNotification::class)->where('type', 'unlock_request');
    }

    /**
     * On donne la date de la demande de réouverture en attente, sinon null. Une demande est
     * en attente tant qu'on n'a pas rouvert la fiche après elle : la réouverture la clôt
     * d'elle-même. Quand la requête a chargé withMax('unlockRequests', 'created_at'), on s'en
     * sert pour ne pas refaire une requête par fiche.
     */
    public function pendingUnlockRequestAt(): ?Carbon
    {
        $latest = array_key_exists('unlock_requests_max_created_at', $this->attributes)
            ? $this->attributes['unlock_requests_max_created_at']
            : $this->unlockRequests()->max('created_at');
        if ($latest === null) {
            return null;
        }
        $latest = Carbon::parse($latest);

        return $this->unlocked_at === null || $latest->gt($this->unlocked_at) ? $latest : null;
    }

    /**
     * L'observation des participants (section 4).
     *
     * @return HasOne<ParticipantEvaluation, $this>
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(ParticipantEvaluation::class);
    }

    /**
     * Les exercices de la séance, dans l'ordre de saisie.
     *
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('position');
    }

    /**
     * Les artistes présents à la séance, avec leur rôle (principal ou ponctuel).
     *
     * @return BelongsToMany<User, $this>
     */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'logbook_entry_user')->withPivot('member_role');
    }

    /**
     * On vérifie si l'échéance de modification est dépassée. On compare les dates seules :
     * la fiche reste modifiable jusqu'à la fin du dernier jour.
     */
    public function deadlinePassed(): bool
    {
        return $this->edit_deadline !== null && self::localNow()->toDateString() > $this->edit_deadline->toDateString();
    }
}
