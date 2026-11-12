<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Section 4 de la fiche : l'observation des participants. On ne note jamais un élève,
 * seulement le groupe.
 *
 * @property int $id
 * @property int $logbook_entry_id
 * @property int|null $engagement
 * @property int|null $expression
 * @property int|null $autonomy
 * @property string|null $interaction
 * @property string|null $feeling
 * @property string|null $feeling_detail
 * @property string|null $progress
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ParticipantEvaluation extends Model
{
    /** Les colonnes de l'observation que l'auteur de la fiche peut remplir. */
    public const FIELDS = ['engagement', 'expression', 'autonomy', 'interaction', 'feeling', 'feeling_detail', 'progress'];

    /** Les ressentis proposés, tels qu'on les stocke (colonne `feeling`). */
    public const FEELINGS = ['well_being', 'discomfort', 'tension', 'buy_in', 'enthusiasm', 'shyness', 'other'];

    /** Les évolutions proposées, telles qu'on les stocke (colonne `progress`). */
    public const PROGRESS = ['first_session', 'improvement', 'stagnation', 'regression'];

    /**
     * Les mots-clés que le cahier des charges suggère pour l'interaction. On les compte dans
     * les statistiques à partir de leurs débuts de mot, sans accent : « conflictuel » compte
     * pour « conflit ».
     */
    public const INTERACTION_KEYWORDS = ['écoute' => ['ecout'], 'entraide' => ['entraid'], 'conflit' => ['conflit', 'conflict']];

    /** @var list<string> */
    protected $fillable = self::FIELDS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engagement' => 'integer',
            'expression' => 'integer',
            'autonomy' => 'integer',
        ];
    }
}
