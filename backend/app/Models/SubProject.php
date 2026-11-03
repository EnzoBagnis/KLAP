<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Sous-projet rattaché à un projet principal.
 * Correspond à un terrain ou une entité pédagogique précise :
 * - Une classe spécifique (ex: Seconde 3, option danse, 15-16 ans)
 * - Un collège spécifique pour BRAISES (ex: Collège Manet, Collège Rosa Parks...)
 * Chaque sous-projet possède son propre compteur de séances.
 */
class SubProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',         // ID du projet parent
        'name',               // Nom du sous-projet (ex: Seconde 3, Collège Édouard Manet)
        'organization',       // Nom de l'établissement scolaire ou de l'association
        'class_group',        // Niveau de classe (ex: 2nde, 3ème, CM1-CM2)
        'age_range',          // Tranche d'âge (ex: 15-16 ans)
        'city',               // Ville de l'établissement
        'postal_code',        // Code postal
        'expected_headcount', // Effectif théorique attendu
        'is_active',          // Statut actif
    ];

    protected function casts(): array
    {
        return [
            'expected_headcount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Projet général auquel appartient ce sous-projet.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Séances qui concernent ce sous-projet.
     * Le champ pivot 'attendees' stocke le nombre de présents réels à chaque séance.
     * C'est sur ce champ qu'on applique la règle du MAX pour les bilans !
     */
    public function scheduledSessions(): BelongsToMany
    {
        return $this->belongsToMany(ScheduledSession::class, 'scheduled_session_sub_project')
            ->withPivot(['attendees', 'project_id']);
    }
}
