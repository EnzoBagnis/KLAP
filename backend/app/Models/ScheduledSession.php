<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ScheduledSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Séance programmée au planning. Elle existe avant la fiche, qui en hérite
 * (date, horaires, lieu, référent). Une séance transversale couvre plusieurs
 * sous-projets du même projet ; on porte le nombre de présents sur le pivot.
 *
 * @property int $id
 * @property int $project_id
 * @property Carbon $session_date
 * @property string $start_time
 * @property string $end_time
 * @property int $location_id
 * @property int $referent_id
 * @property string $session_type
 * @property string|null $session_type_detail
 * @property string $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Location $location
 * @property-read User $referent
 * @property-read LogbookEntry|null $logbookEntry
 * @property-read Collection<int, SubProject> $subProjects
 *
 * @method static Builder<static> visibleTo(User $user)
 */
class ScheduledSession extends Model
{
    /** @use HasFactory<ScheduledSessionFactory> */
    use HasFactory;

    /** Les types de séance du cahier des charges, tels qu'on les stocke (colonne `session_type`). */
    public const TYPES = [
        'guided_tour', 'viewing_workshop', 'practice_workshop',
        'complementary_workshop', 'performance', 'ongoing_project', 'other',
    ];

    /** @var list<string> */
    protected $fillable = [
        'session_date',
        'start_time',
        'end_time',
        'location_id',
        'referent_id',
        'session_type',
        'session_type_detail',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    /**
     * On limite la requête aux séances que l'utilisateur voit au planning. Administratrice et
     * super-utilisatrice : toutes. Intervenant : celles de ses projets, plus celles dont il
     * est référent.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::SuperUser)) {
            return $query;
        }
        if (! $user->hasRole(UserRole::Contributor)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $mine) => $mine
            ->where('scheduled_sessions.referent_id', $user->id)
            ->orWhereHas('project', fn (Builder $project) => $project->visibleTo($user)));
    }

    /**
     * On cherche une séance non annulée du même référent, le même jour, dont l'horaire
     * chevauche celui donné (#168). On compare en PHP sur « HH:MM » : SQLite et MariaDB ne
     * stockent pas l'heure au même format. Deux séances accolées (10 h - 12 h, puis
     * 12 h - 14 h) ne se chevauchent pas.
     *
     * @param  string  $date  au format AAAA-MM-JJ
     * @param  string  $start  « HH:MM » ou « HH:MM:SS »
     * @param  string  $end  « HH:MM » ou « HH:MM:SS »
     * @param  int|null  $ignoredId  la séance qu'on est en train de modifier
     * @return object{start_time: string, end_time: string, project_name: string}|null
     */
    public static function overlapping(int $referentId, string $date, string $start, string $end, ?int $ignoredId = null): ?object
    {
        $start = substr($start, 0, 5);
        $end = substr($end, 0, 5);

        return DB::table('scheduled_sessions as s')
            ->join('projects as p', 'p.id', '=', 's.project_id')
            ->where('s.referent_id', $referentId)
            ->whereDate('s.session_date', $date)
            ->where('s.status', '<>', 'cancelled')
            ->when($ignoredId, fn ($query, $id) => $query->where('s.id', '<>', $id))
            ->get(['s.start_time', 's.end_time', 'p.name as project_name'])
            ->first(fn ($other) => substr((string) $other->start_time, 0, 5) < $end && $start < substr((string) $other->end_time, 0, 5));
    }

    /**
     * Le message du refus de chevauchement, qu'on affiche sous « Début ».
     *
     * @param  object{start_time: string, end_time: string, project_name: string}  $clash
     */
    public static function overlapMessage(object $clash): string
    {
        return sprintf('Cet·te intervenant·e a déjà une séance de %s à %s ce jour-là (%s).',
            substr((string) $clash->start_time, 0, 5), substr((string) $clash->end_time, 0, 5), $clash->project_name);
    }

    /**
     * Le projet de la séance.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Le lieu de la séance.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * L'intervenant·e qui mène la séance et remplit sa fiche.
     *
     * @return BelongsTo<User, $this>
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referent_id');
    }

    /**
     * La fiche de la séance, si elle a été commencée.
     *
     * @return HasOne<LogbookEntry, $this>
     */
    public function logbookEntry(): HasOne
    {
        return $this->hasOne(LogbookEntry::class);
    }

    /**
     * Les groupes concernés, avec le nombre de présents de chacun sur le pivot.
     *
     * @return BelongsToMany<SubProject, $this>
     */
    public function subProjects(): BelongsToMany
    {
        return $this->belongsToMany(SubProject::class, 'scheduled_session_sub_project', 'scheduled_session_id', 'sub_project_id')
            ->withPivot(['project_id', 'attendees']);
    }
}
