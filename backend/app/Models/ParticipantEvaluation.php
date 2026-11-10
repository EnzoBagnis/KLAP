<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Section 4 de la fiche : observation des participants. */
class ParticipantEvaluation extends Model
{
    public const FIELDS = ['engagement', 'expression', 'autonomy', 'interaction', 'feeling', 'feeling_detail', 'progress'];

    public const FEELINGS = ['well_being', 'discomfort', 'tension', 'buy_in', 'enthusiasm', 'shyness', 'other'];

    public const PROGRESS = ['first_session', 'improvement', 'stagnation', 'regression'];

    /** Mots-clés suggérés pour l'interaction (cahier des charges), comptés dans les statistiques. */
    public const INTERACTION_KEYWORDS = ['écoute', 'entraide', 'conflit'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return [
            'engagement' => 'integer',
            'expression' => 'integer',
            'autonomy' => 'integer',
        ];
    }
}
