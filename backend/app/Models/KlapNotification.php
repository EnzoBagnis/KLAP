<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification interne (cloche). Types de la migration : entry_submitted, unlock_request,
 * invitation_accepted, reminder. Repris de la branche de Lucas (#121).
 */
class KlapNotification extends Model
{
    public const MAX_LISTED = 30;

    /** Message par type : qui agit, date de la séance, projet et groupes (#122, #130). */
    public const MESSAGES = [
        'entry_submitted' => '%s a rempli la fiche de la séance du %s : %s (%s).',
        'unlock_request' => '%s demande à rouvrir la fiche de la séance du %s : %s (%s).',
    ];

    /**
     * Texte d'une notification liée à une fiche. La colonne est un VARCHAR(255) : un nom de
     * projet très long est tronqué.
     */
    public static function messageFor(string $type, string $actorName, LogbookEntry $entry): string
    {
        $entry->loadMissing(['scheduledSession.project', 'scheduledSession.subProjects']);
        $session = $entry->scheduledSession;

        return mb_strimwidth(sprintf(self::MESSAGES[$type],
            $actorName,
            $session->session_date->locale('fr')->translatedFormat('j F'),
            $session->project->name,
            $session->subProjects->pluck('name')->join(', ')), 0, 255, '…');
    }

    protected $table = 'klap_notifications';

    protected $fillable = ['recipient_id', 'type', 'logbook_entry_id', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class);
    }

    /** Non lues, pour le compteur de la cloche. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
