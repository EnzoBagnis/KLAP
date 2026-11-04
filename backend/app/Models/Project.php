<?php

namespace App\Models;

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
