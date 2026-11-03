<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projet d'action artistique (ex: Arts Danse au Lycée Charles, BRAISES...).
 * C'est le conteneur principal qui regroupe les sous-projets (classes/groupes),
 * les séances planifiées, les artistes intervenants et les exports de bilans.
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',                // Titre officiel du projet
        'description',         // Description ou notes d'intention
        'start_date',          // Date de début
        'end_date',            // Date de fin
        'status',              // Statut : draft, active, finished, archived
        'default_location_id', // Lieu principal assigné par défaut
        'created_by',          // Utilisateur qui a créé le projet (généralement l'admin)
        'archived_at',         // Horodatage si le projet a été archivé
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Filtres réutilisables)
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Lieu habituel ou par défaut du projet.
     */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * Administrateur ayant créé la fiche projet.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Financeurs associés à ce projet.
     */
    public function funders(): BelongsToMany
    {
        return $this->belongsToMany(Funder::class, 'funder_project');
    }

    /**
     * Les sous-projets (classes ou collèges distincts liés à ce projet).
     * Ex: Seconde 3, Seconde 7, Première 2, Terminale 5 pour le Lycée Charles.
     */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class);
    }

    /**
     * Toutes les séances d'atelier programmées sur ce projet.
     */
    public function scheduledSessions(): HasMany
    {
        return $this->hasMany(ScheduledSession::class);
    }

    /**
     * Tous les artistes impliqués sur le projet (principaux et ponctuels).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['member_role', 'start_date', 'end_date']);
    }

    /**
     * L'intervenant référent principal (désigné par l'admin à la création).
     */
    public function leadArtist(): BelongsToMany
    {
        return $this->users()->wherePivot('member_role', 'lead');
    }

    /**
     * Intervenants ponctuels (ajoutés automatiquement au fur et à mesure des fiches).
     */
    public function occasionalArtists(): BelongsToMany
    {
        return $this->users()->wherePivot('member_role', 'occasional');
    }

    /**
     * Champs personnalisés configurés pour ce projet par l'admin.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * Bilans exportés pour ce projet.
     */
    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }
}
