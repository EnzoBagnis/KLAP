<?php

namespace App\Models;

use Database\Factories\ProjectTypeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Type de projet (EAC, médiation…). C'est une liste que l'administratrice tient elle-même :
 * on ne supprime jamais un type, on le désactive.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Project> $projects
 */
class ProjectType extends Model
{
    /** @use HasFactory<ProjectTypeFactory> */
    use HasFactory;

    /**
     * On reprend la valeur par défaut de la base, pour que la réponse de création la porte.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Les projets de ce type.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
