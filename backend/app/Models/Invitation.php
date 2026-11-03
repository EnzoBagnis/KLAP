<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitations utilisateurs envoyées par l'administratrice.
 * Pour des raisons de sécurité, il n'existe pas d'inscription libre :
 * 1. L'admin invite une adresse email en lui attribuant un rôle
 * 2. Un token temporisé à 7 jours est généré et haché en SHA-256
 * 3. L'artiste clique sur le lien et choisit son mot de passe pour activer son compte.
 */
class Invitation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'email',       // Adresse email invitée
        'full_name',   // Nom complet de la personne
        'role',        // Rôle attribué : admin, super_user, contributor
        'token_hash',  // Empreinte SHA-256 du jeton d'invitation (sécurité)
        'invited_by',  // Admin qui a émis l'invitation
        'user_id',     // Compte utilisateur créé une fois l'invitation acceptée
        'sent_at',     // Date et heure d'envoi
        'expires_at',  // Date d'expiration (7 jours après envoi)
        'accepted_at', // Date et heure de l'activation du compte
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers métier
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', Carbon::now());
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * L'administrateur ayant envoyé cette invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * L'utilisateur créé suite à l'acceptation de l'invitation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
