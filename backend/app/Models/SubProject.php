<?php

namespace App\Models;

use Database\Factories\SubProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Groupe d'un projet : une classe, un établissement. Dans l'interface on dit « groupe » ;
 * en base on a gardé « sous-projet », le mot du cahier des charges. Un projet a toujours au
 * moins un groupe actif.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $organization
 * @property string|null $class_group
 * @property string|null $age_range
 * @property string|null $city
 * @property string|null $postal_code
 * @property int|null $expected_headcount
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Pivot $pivot la ligne de liaison, quand on a chargé le modèle par une relation plusieurs-à-plusieurs
 */
class SubProject extends Model
{
    /** @use HasFactory<SubProjectFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'organization',
        'class_group',
        'age_range',
        'city',
        'postal_code',
        'expected_headcount',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_headcount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Le projet auquel le groupe appartient.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
