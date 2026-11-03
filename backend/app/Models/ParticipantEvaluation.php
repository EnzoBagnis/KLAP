<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Temps 4 de la fiche carnet de bord : « Les participants ».
 * Permet d'observer le groupe sans noter scolairement :
 * - 3 jauges de 1 à 5 étoiles (Engagement, Expression, Autonomie)
 * - Mots-clés d'interaction (écoute, entraide, conflit, soutien, retrait)
 * - Sensations dominantes (bien-être, mal-être, tensions, enthousiasme...)
 * - Évolution par rapport à la séance précédente (première séance, amélioration, stagnation...)
 */
class ParticipantEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'logbook_entry_id', // ID de la fiche liée
        'engagement',       // Note de 1 à 5 étoiles
        'expression',       // Note de 1 à 5 étoiles
        'autonomy',         // Note de 1 à 5 étoiles
        'interaction',      // Mots-clés suggérés ou texte libre (max 100 car)
        'feeling',          // Menu déroulant : well_being, discomfort, tension, buy_in, enthusiasm, shyness, other
        'feeling_detail',   // Précision obligatoire si 'other' est sélectionné
        'progress',         // Évolution : first_session, improvement, stagnation, regression
    ];

    protected function casts(): array
    {
        return [
            'engagement' => 'integer',
            'expression' => 'integer',
            'autonomy' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Fiche de carnet de bord parente.
     */
    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class);
    }
}
