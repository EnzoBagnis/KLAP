<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Système de notifications internes de l'application.
 * Alerte l'administratrice (ou les intervenants) lors d'événements clés :
 * - Une fiche de séance a été remplie
 * - Un artiste demande le déverrouillage d'une fiche après J+10
 * - Un compte a été activé via une invitation
 */
class KlapNotification extends Model
{
    use HasFactory;

    protected $table = 'klap_notifications';

    protected $fillable = [
        'recipient_id',     // Utilisateur destinataire de la notification
        'type',             // Type : entry_submitted, unlock_request, invitation_accepted, reminder
        'logbook_entry_id', // Fiche de carnet de bord concernée (si applicable)
        'message',          // Texte du message affiché dans la cloche
        'read_at',          // Horodatage de lecture (null = non lu, pastille rouge active)
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers & Scopes
    |--------------------------------------------------------------------------
    */

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): bool
    {
        return $this->update(['read_at' => Carbon::now()]);
    }

    /**
     * Uniquement les notifications non lues (pour le compteur de la cloche).
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class);
    }
}
