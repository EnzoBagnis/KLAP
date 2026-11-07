<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
