<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Financeurs des actions artistiques.
 * Exemples : Ville de Marseille, Ministère de la Culture, Fondations privées.
 * Destinataires finaux des bilans et des statistiques pour justifier les financements.
 */
class Funder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', // Nom de l'organisme financeur
        'type', // Type : state, local_authority, foundation, private, other
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Projets subventionnés par ce financeur.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'funder_project');
    }

    /**
     * Historique des bilans générés spécifiquement pour ce financeur.
     */
    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }
}
