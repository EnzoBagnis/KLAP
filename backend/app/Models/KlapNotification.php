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
