<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation à créer un compte (contexte client : pas d'inscription libre). Le jeton envoyé
 * par mail n'est jamais stocké : seule son empreinte sha256 l'est. Repris de la branche de
 * Lucas (#121), rôle casté en enum.
 */
class Invitation extends Model
{
    use HasFactory;

    /** Durée de validité (choix d'équipe, à confirmer avec la cliente). */
    public const VALID_DAYS = 7;

    public $timestamps = false;

    /** Renseigné à la création : une invitation en cours a-t-elle été remplacée ? */
    public bool $replaced = false;

    protected $fillable = ['email', 'full_name', 'role', 'token_hash', 'invited_by', 'sent_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** Ni acceptée ni expirée. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
