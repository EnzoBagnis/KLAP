<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Séance programmée dans le planning.
 * Créée à l'avance par l'administratrice (date, horaire, lieu, intervenant).
 * Cette séance sert à générer automatiquement la fiche de carnet de bord pré-remplie
 * que l'artiste n'aura plus qu'à compléter après son intervention.
 */
class ScheduledSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',           // Projet concerné
        'session_date',         // Date de la séance
        'start_time',           // Heure de début
        'end_time',             // Heure de fin
        'location_id',          // Lieu où se déroule l'atelier
        'referent_id',          // Artiste désigné pour mener la séance et remplir la fiche
        'session_type',         // Type d'action (guided_tour, viewing_workshop, practice_workshop...)
        'session_type_detail',  // Précision textuelle si le type choisi est 'other'
        'is_transversal',       // Vrai si la séance regroupe tous les groupes (affichée en rouge sur le planning)
        'status',               // planned, done, cancelled
        'created_by',           // Utilisateur qui a planifié la séance
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'is_transversal' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Filtres réutilisables pour le planning)
    |--------------------------------------------------------------------------
    */

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    public function scopeTransversal(Builder $query): Builder
    {
        return $query->where('is_transversal', true);
    }

    /**
     * Filtre les séances pour un mois précis (utile pour l'affichage du planning par onglets de mois).
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('session_date', $year)
            ->whereMonth('session_date', $month);
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * L'artiste intervenant qui doit remplir la fiche de carnet de bord.
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Sous-projets (classes ou collèges) qui participent à cette séance.
     */
    public function subProjects(): BelongsToMany
    {
        return $this->belongsToMany(SubProject::class, 'scheduled_session_sub_project')
            ->withPivot(['attendees', 'project_id']);
    }

    /**
     * Fiche de carnet de bord associée à cette séance (une seule fiche par séance).
     */
    public function logbookEntry(): HasOne
    {
        return $this->hasOne(LogbookEntry::class);
    }
}
