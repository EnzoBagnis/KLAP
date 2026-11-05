<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Champs personnalisés ajoutés par l'admin sur un projet spécifique.
 * Permet d'enrichir les fiches de séance avec des questions sur-mesure
 * adaptées à un projet particulier (ex: une question spécifique sur un spectacle).
 */
class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',     // Projet pour lequel ce champ est actif
        'section',        // Section visée : general, objectives, session, participants, artists, next_steps
        'label',          // Intitulé de la question
        'field_type',     // Type d'input : text, number, select, checkbox, scale
        'options',        // Choix possibles au format JSON (pour les menus déroulants)
        'is_required',    // Champ obligatoire ou facultatif
        'position',       // Ordre d'affichage dans la section
        'is_active',      // Permet de désactiver un champ sans perdre les anciennes données
        'activated_at',   // Date d'activation
        'deactivated_at', // Date de désactivation
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
