<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Fiche de séance (carnet de bord). Elle hérite de sa séance (date, lieu, groupes)
 * et suit le cycle brouillon, soumise, verrouillée (J+10).
 */
class LogbookEntry extends Model
{
    /** Colonnes du socle de la fiche, modifiables par son auteur. */
    public const FIELDS = [
        'objectives', 'adjustment_done', 'adjustment_detail', 'highlights', 'tools_resources',
        'learnings', 'artist_review', 'next_steps', 'dropped_leads', 'keep_in_class', 'additional_notes',
    ];

    /** Délai de modification après la séance, en jours (contexte client : verrouillage à J+10). */
    public const EDIT_DAYS = 10;

    /** Les échéances se comptent à l'heure de KLAP, pas en UTC (l'application reste en UTC). */
    public const LOCAL_TIMEZONE = 'Europe/Paris';

    public static function localNow(): Carbon
    {
        return now(self::LOCAL_TIMEZONE);
    }

    protected $fillable = self::FIELDS;

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

    public function scheduledSession(): BelongsTo
    {
        return $this->belongsTo(ScheduledSession::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(ParticipantEvaluation::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('position');
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'logbook_entry_user')->withPivot('member_role');
    }

    /** L'échéance de modification est-elle dépassée ? (comparaison sur la date seule) */
    public function deadlinePassed(): bool
    {
        return $this->edit_deadline !== null && self::localNow()->toDateString() > $this->edit_deadline->toDateString();
    }
}
