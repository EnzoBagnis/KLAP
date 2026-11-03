<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Utilisateur de l'application KLAP.
 * Gère les 3 rôles de l'organisation :
 * - 'admin' : Mariette Travard (accès complet, création des projets et planning, validation des déverrouillages, bilans)
 * - 'super_user' : Emilie Garetier (coordination, création et modification de fiches)
 * - 'contributor' : Les artistes/intervenants (Léa Fournier, Marco Delaunay... saisissent leurs fiches sous 10 jours).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Colonnes autorisées à être renseignées massivement (protection anti-piratage).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
    ];

    /**
     * Attributs masqués lors de l'envoi en JSON à React.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Typage automatique des colonnes en objets PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de rôles (RBAC)
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperUser(): bool
    {
        return $this->role === 'super_user';
    }

    public function isContributor(): bool
    {
        return $this->role === 'contributor';
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Projets auxquels l'utilisateur est assigné (en tant qu'artiste principal ou ponctuel).
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot(['member_role', 'start_date', 'end_date'])
            ->withTimestamps();
    }

    /**
     * Projets créés par cet utilisateur.
     */
    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    /**
     * Séances planifiées dont cet utilisateur est l'intervenant référent.
     */
    public function scheduledSessions(): HasMany
    {
        return $this->hasMany(ScheduledSession::class, 'referent_id');
    }

    /**
     * Fiches de carnet de bord rédigées par cet utilisateur.
     */
    public function logbookEntries(): HasMany
    {
        return $this->hasMany(LogbookEntry::class, 'author_id');
    }

    /**
     * Fiches pour lesquelles l'artiste a participé (P5 - Les artistes).
     */
    public function attendedLogbookEntries(): BelongsToMany
    {
        return $this->belongsToMany(LogbookEntry::class, 'logbook_entry_user')
            ->withPivot('member_role');
    }

    /**
     * Invitations envoyées par cet administrateur.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * Notifications reçues par cet utilisateur.
     */
    public function klapNotifications(): HasMany
    {
        return $this->hasMany(KlapNotification::class, 'recipient_id');
    }
}
