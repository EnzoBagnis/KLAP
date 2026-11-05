<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lieux d'intervention des ateliers.
 * Comprend les 3 studios permanents de KLAP (Petit studio, Grand studio, Salle de création)
 * ainsi que les lieux partenaires extérieurs (écoles, collèges, théâtres en tournée).
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',        // Nom du studio ou de la salle
        'structure',   // Nom de l'établissement ou de la structure partenaire (ex: Collège Manet)
        'address',     // Adresse postale
        'postal_code', // Code postal (5 chiffres obligatoires)
        'city',        // Ville
        'is_klap',     // Vrai si c'est l'un des studios internes de KLAP
        'is_active',   // Permet de désactiver un lieu sans le supprimer
        'created_by',  // Utilisateur qui a ajouté ce lieu
    ];

    protected function casts(): array
    {
        return [
            'is_klap' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Filtres réutilisables)
    |--------------------------------------------------------------------------
    */

    /**
     * Lieux actifs disponibles dans les formulaires.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Uniquement les studios internes de KLAP.
     */
    public function scopeKlap(Builder $query): Builder
    {
        return $query->where('is_klap', true);
    }

    /**
     * Uniquement les lieux extérieurs (projets en tournée / hors les murs).
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_klap', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Utilisateur qui a créé ce lieu.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Projets qui utilisent ce lieu comme adresse par défaut.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'default_location_id');
    }

    /**
     * Séances planifiées se déroulant dans ce lieu.
     */
    public function scheduledSessions(): HasMany
    {
        return $this->hasMany(ScheduledSession::class);
    }
}
