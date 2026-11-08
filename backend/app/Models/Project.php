<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    /** Statuts autorisés à la création : un projet ne naît ni terminé ni archivé. */
    public const CREATION_STATUSES = ['draft', 'active'];

    /** Statuts qu'on choisit en modifiant un projet ; l'archivage a sa propre route (#152). */
    public const EDITABLE_STATUSES = ['draft', 'active', 'finished'];

    protected $fillable = [
        'name',
        'description',
        'project_type_id',
        'planned_sessions',
        'start_date',
        'end_date',
        'status',
        'default_location_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'planned_sessions' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Projets qu'un utilisateur peut consulter (RG02) : tous pour l'administratrice et la
     * super-utilisatrice, seulement ceux où il est affecté (principal ou ponctuel) pour un
     * intervenant. Le statut n'entre pas en compte : un brouillon affecté reste visible.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::SuperUser)) {
            return $query;
        }

        if ($user->hasRole(UserRole::Contributor)) {
            return $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        }

        // Rôle inconnu (ajouté plus tard à l'énumération) : rien, par défaut.
        return $query->whereRaw('1 = 0');
    }

    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * Équipe du projet (AFFECTATION) : l'intervenant·e principal·e (`lead`), désigné·e
     * à la création, et les ponctuel·le·s (`occasional`), ajouté·e·s par les fiches.
     */
    public function members(): BelongsToMany
    {
        // Principal·e d'abord (« lead » précède « occasional », en texte comme dans l'enum), puis par nom.
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['member_role', 'start_date', 'end_date'])
            ->orderBy('project_user.member_role')
            ->orderBy('users.name');
    }

    public function funders(): BelongsToMany
    {
        return $this->belongsToMany(Funder::class)->orderBy('funders.name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
