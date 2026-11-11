<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Séance programmée au planning. Elle existe avant la fiche, qui en hérite
 * (date, horaires, lieu, référent). Une séance transversale couvre plusieurs
 * sous-projets du même projet ; le nombre de présents est porté par le pivot.
 */
class ScheduledSession extends Model
{
    use HasFactory;

    public const TYPES = [
        'guided_tour', 'viewing_workshop', 'practice_workshop',
        'complementary_workshop', 'performance', 'ongoing_project', 'other',
    ];

    protected $fillable = [
        'session_date',
        'start_time',
        'end_time',
        'location_id',
        'referent_id',
        'session_type',
        'session_type_detail',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    /**
     * Séances visibles au planning. Administratrice et super-utilisatrice : toutes.
     * Intervenant : celles de ses projets, plus celles dont il est référent.
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
     * Séance non annulée du même référent, le même jour, dont l'horaire chevauche celui donné
     * (#168). Comparaison en PHP sur « HH:MM » : SQLite et MariaDB ne stockent pas l'heure au même
     * format, et deux séances accolées (10 h - 12 h, puis 12 h - 14 h) ne se chevauchent pas.
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

    /** Message du refus de chevauchement, sous « Début ». */
    public static function overlapMessage(object $clash): string
    {
        return sprintf('Cet·te intervenant·e a déjà une séance de %s à %s ce jour-là (%s).',
            substr((string) $clash->start_time, 0, 5), substr((string) $clash->end_time, 0, 5), $clash->project_name);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function referent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referent_id');
    }

    public function logbookEntry(): HasOne
    {
        return $this->hasOne(LogbookEntry::class);
    }

    public function subProjects(): BelongsToMany
    {
        return $this->belongsToMany(SubProject::class, 'scheduled_session_sub_project', 'scheduled_session_id', 'sub_project_id')
            ->withPivot(['project_id', 'attendees']);
    }
}
