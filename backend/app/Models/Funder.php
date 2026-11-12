<?php

namespace App\Models;

use Database\Factories\FunderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Financeur d'un ou de plusieurs projets. Le pré-bilan se prépare par financeur. On ne le
 * supprime jamais : désactivé, il n'est plus proposé, mais les projets qu'il finance le gardent.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Project> $projects
 */
class Funder extends Model
{
    /** @use HasFactory<FunderFactory> */
    use HasFactory;

    /** Les catégories (colonne `type`) : État, collectivité, fondation, autre. */
    public const TYPES = ['state', 'local_authority', 'foundation', 'other'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Les projets que ce financeur soutient.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
