<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique des bilans et pré-bilans exportés.
 * Permet de conserver une trace des documents générés pour les financeurs :
 * - Export Word (.docx) : document rédigé et modifiable
 * - Export Excel (.xlsx) / CSV : tableaux de chiffres pour calculs
 * NB : Conformément au cahier des charges de Mariette, aucun PDF n'est produit.
 */
class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',   // Projet concerné par le bilan
        'funder_id',    // Financeur ciblé (optionnel)
        'generated_by', // Utilisateur qui a généré l'export
        'period_start', // Date de début de la période évaluée
        'period_end',   // Date de fin de la période évaluée
        'options',      // Tableau JSON des sections cochées lors de la génération
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'options' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
