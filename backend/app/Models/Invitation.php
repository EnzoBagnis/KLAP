<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation à créer un compte (contexte client : pas d'inscription libre). On ne stocke
 * jamais le jeton envoyé par mail, seulement son empreinte sha256. On est repartis de la
 * branche de Lucas (#121), avec le rôle casté en enum.
 *
 * @property int $id
 * @property string $email
 * @property string $full_name
 * @property UserRole $role
 * @property string $token_hash
 * @property int $invited_by
 * @property int|null $user_id
 * @property Carbon $sent_at
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read User $inviter
 *
 * @method static Builder<static> pending()
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /** La durée de validité, en jours (un choix de l'équipe, à confirmer avec la cliente). */
    public const VALID_DAYS = 7;

    /** On n'a ni created_at ni updated_at : sent_at et accepted_at suffisent. */
    public $timestamps = false;

    /** On le renseigne à la création : a-t-on remplacé une invitation encore en cours ? */
    public bool $replaced = false;

    /** @var list<string> */
    protected $fillable = ['email', 'full_name', 'role', 'token_hash', 'invited_by', 'sent_at', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * L'empreinte du jeton, la seule forme qu'on garde en base.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * L'administratrice qui a envoyé l'invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * On vérifie que l'invitation peut encore servir : ni acceptée, ni expirée.
     */
    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * On ne garde que les invitations en cours : ni acceptées, ni expirées.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
