<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification interne (cloche). Types de la migration : entry_submitted, unlock_request,
 * invitation_accepted, reminder. On est repartis de la branche de Lucas (#121).
 *
 * @property int $id
 * @property int $recipient_id
 * @property string $type
 * @property int|null $logbook_entry_id
 * @property string $message
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $recipient
 * @property-read LogbookEntry|null $logbookEntry
 *
 * @method static Builder<static> unread()
 */
class KlapNotification extends Model
{
    /** Le nombre de notifications qu'on liste sous la cloche. */
    public const MAX_LISTED = 30;

    /** Le message de chaque type : qui agit, la date de la séance, le projet et ses groupes (#122, #130). */
    public const MESSAGES = [
        'entry_submitted' => '%s a rempli la fiche de la séance du %s : %s.',
        'unlock_request' => '%s demande à rouvrir la fiche de la séance du %s : %s.',
    ];

    /**
     * On compose le texte d'une notification liée à une fiche. La colonne est un
     * VARCHAR(255) : on tronque un nom de projet très long.
     *
     * @param  string  $type  une clé de MESSAGES
     * @param  string  $actorName  la personne à l'origine de la notification
     */
    public static function messageFor(string $type, string $actorName, LogbookEntry $entry): string
    {
        $entry->loadMissing(['scheduledSession.project', 'scheduledSession.subProjects']);
        $session = $entry->scheduledSession;
        // On donne le projet et ses groupes ; un groupe unique qui porte le nom du projet, on ne
        // le répète pas (« Étincelles (Étincelles) »).
        $groups = $session->subProjects->pluck('name')->join(', ');
        $where = $groups === '' || $groups === $session->project->name
            ? $session->project->name
            : "{$session->project->name} ({$groups})";

        return mb_strimwidth(sprintf(self::MESSAGES[$type],
            $actorName,
            $session->session_date->locale('fr')->translatedFormat('j F'),
            $where), 0, 255, '…');
    }

    /** On nomme la table nous-mêmes : « notifications » est déjà prise par Laravel. */
    protected $table = 'klap_notifications';

    /** @var list<string> */
    protected $fillable = ['recipient_id', 'type', 'logbook_entry_id', 'message', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /**
     * La personne qui reçoit la notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * La fiche concernée, quand la notification en vise une.
     *
     * @return BelongsTo<LogbookEntry, $this>
     */
    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class);
    }

    /**
     * On ne garde que les notifications non lues, pour le compteur de la cloche.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
