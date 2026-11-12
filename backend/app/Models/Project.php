<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Projet d'action culturelle : le cœur de l'application. Il regroupe des groupes
 * (sous-projets), une équipe d'intervenant·e·s, des financeurs et des séances.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $project_type_id
 * @property int|null $planned_sessions
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string $status
 * @property int $default_location_id
 * @property int $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SubProject> $subProjects
 * @property-read ProjectType|null $projectType
 * @property-read Location $defaultLocation
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Funder> $funders
 * @property-read User $creator
 *
 * @method static Builder<static> visibleTo(User $user)
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** Les statuts qu'on accepte à la création : un projet ne naît ni terminé ni archivé. */
    public const CREATION_STATUSES = ['draft', 'active'];

    /** Les statuts qu'on peut choisir en modifiant un projet ; l'archivage a sa propre route (#152). */
    public const EDITABLE_STATUSES = ['draft', 'active', 'finished'];

    /** @var list<string> */
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

    /**
     * @return array<string, string>
     */
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
     * On limite la requête aux projets que l'utilisateur peut consulter (RG02) : tous pour
     * l'administratrice et la super-utilisatrice, seulement ceux où il est affecté (principal
     * ou ponctuel) pour un intervenant. On ne regarde pas le statut : un brouillon auquel on
     * est affecté reste visible.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::SuperUser)) {
            return $query;
        }

        if ($user->hasRole(UserRole::Contributor)) {
            // On ne garde que les membres actuels (#251) : une fois retiré de l'équipe, on ne
            // voit plus le projet. Ses propres séances et fiches restent lisibles par ailleurs.
            return $query->whereHas('members', fn (Builder $members) => self::currentMember($members, $user));
        }

        // Pour un rôle qu'on ajouterait plus tard à l'énumération, on ne montre rien par défaut.
        return $query->whereRaw('1 = 0');
    }

    /**
     * On restreint une requête sur l'équipe à l'appartenance en cours de l'utilisateur :
     * sans date de fin, ou avec une date de fin qui n'est pas encore passée.
     *
     * @param  Builder<User>  $members
     * @return Builder<User>
     */
    public static function currentMember(Builder $members, User $user): Builder
    {
        return $members->whereKey($user->id)->where(fn (Builder $current) => $current
            ->whereNull('project_user.end_date')
            ->orWhereDate('project_user.end_date', '>=', LogbookEntry::localNow()->toDateString()));
    }

    /**
     * Les groupes du projet, actifs ou retirés.
     *
     * @return HasMany<SubProject, $this>
     */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class);
    }

    /**
     * Le type du projet, s'il en a un.
     *
     * @return BelongsTo<ProjectType, $this>
     */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * Le lieu habituel, qu'on propose par défaut pour chaque séance.
     *
     * @return BelongsTo<Location, $this>
     */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * L'équipe du projet (AFFECTATION) : l'intervenant·e principal·e (`lead`), qu'on désigne
     * à la création, et les ponctuel·le·s (`occasional`), que l'administratrice ajoute ou
     * retire depuis la page du projet (#251).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        // On met le ou la principal·e d'abord (« lead » précède « occasional », en texte comme
        // dans l'enum), puis on trie par nom.
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['member_role', 'start_date', 'end_date'])
            ->orderBy('project_user.member_role')
            ->orderBy('users.name');
    }

    /**
     * Les financeurs du projet, par ordre alphabétique.
     *
     * @return BelongsToMany<Funder, $this>
     */
    public function funders(): BelongsToMany
    {
        return $this->belongsToMany(Funder::class)->orderBy('funders.name');
    }

    /**
     * La personne qui a créé le projet.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
