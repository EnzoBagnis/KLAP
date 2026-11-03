<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Fiche de carnet de bord remplie par l'intervenant après chaque séance.
 * C'est le cœur de l'application KLAP.
 * Elle se compose de 6 temps de saisie (pensés pour smartphone et desktop) :
 * - Temps 1 : Généralités (effectifs, répartition F/G, classe/âge)
 * - Temps 2 : Objectifs péda & artistiques, compétences, progression
 * - Temps 3 : Déroulé de la séance, exercices, retours, ajustements fait/non fait
 * - Temps 4 : Évaluation des participants (dans la table participant_evaluations)
 * - Temps 5 : Bilan des artistes (ce qui fonctionne, à surveiller, notes)
 * - Temps 6 : La suite (pistes, maintien en classe, souhaits des élèves)
 *
 * Règle de verrouillage :
 * La fiche est verrouillée automatiquement 10 jours après la séance pour garantir
 * la sincérité des observations à chaud. L'admin peut la déverrouiller sur demande motivée.
 */
class LogbookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_session_id', // Séance programmée liée
        'author_id',            // Artiste ayant rédigé la fiche
        'status',               // 'draft' (brouillon), 'submitted' (remplie), 'locked' (verrouillée)

        // --------------------------------------------------------------------
        // P1 - Généralités
        // --------------------------------------------------------------------
        'attendees_count',      // Effectif total présent (max 999)
        'attendees_male',       // Nombre de garçons (obligatoire)
        'attendees_female',     // Nombre de filles (obligatoire)
        'class_or_age',         // Groupe ou tranche d'âge (ex: 2nde — 15-16 ans)

        // --------------------------------------------------------------------
        // P2 - Objectifs
        // --------------------------------------------------------------------
        'objectives',           // Objectifs pédagogiques et artistiques (max 400 car)
        'competencies',         // Compétences en jeu (max 200 car)
        'progression',          // Progression du projet (max 200 car)

        // --------------------------------------------------------------------
        // P3 - La séance
        // --------------------------------------------------------------------
        'highlights',           // Temps forts et anecdotes (5 à 200 car)
        'closing',              // Clôture de l'atelier (max 100 car)
        'feedback',             // Retours des participants (max 500 car)
        'tools_resources',      // Outils et ressources externes (max 200 car)
        'adjustment_done',      // Ajustement : vrai = Fait, faux = Non fait
        'adjustment_detail',    // Pourquoi ? (obligatoire si Fait, 5 à 500 car)

        // --------------------------------------------------------------------
        // P5 - Les artistes
        // --------------------------------------------------------------------
        'what_works',           // Ce qui fonctionne (5 à 200 car)
        'learnings',            // Les acquis constatés (max 200 car)
        'to_watch',             // Points à surveiller (max 200 car)
        'to_improve',           // Pistes à améliorer (max 200 car)
        'additional_notes',     // Notes complémentaires de l'artiste (max 1 000 car)

        // --------------------------------------------------------------------
        // P6 - La suite
        // --------------------------------------------------------------------
        'next_steps',           // Éléments à reprendre à la séance suivante (max 500 car)
        'keep_in_class',        // Case à cocher : maintien en classe avec l'équipe enseignante ?
        'dropped_leads',        // Pistes à abandonner et pourquoi (max 500 car)
        'participant_wishes',   // Idées et envies exprimées par les participants (max 500 car)

        // --------------------------------------------------------------------
        // Verrouillage J+10 & Demande de déverrouillage motivée
        // --------------------------------------------------------------------
        'edit_deadline',        // Date limite d'édition (calculée : date séance + 10 jours)
        'submitted_at',         // Date et heure de soumission finale
        'unlock_reason',        // Motif rédigé par l'artiste pour demander la réouverture
        'unlock_requested_at',  // Date et heure de la demande de réouverture
        'unlocked_by',          // Admin qui a accepté de rouvrir la fiche
        'unlocked_at',          // Date et heure de réouverture accordée
    ];

    protected function casts(): array
    {
        return [
            'attendees_count' => 'integer',
            'attendees_male' => 'integer',
            'attendees_female' => 'integer',
            'adjustment_done' => 'boolean',
            'keep_in_class' => 'boolean',
            'edit_deadline' => 'date',
            'submitted_at' => 'datetime',
            'unlock_requested_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers métier
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'locked'], true);
    }

    /**
     * Vérifie si la fiche est verrouillée en modification.
     * Une fiche est bloquée si :
     * 1. Son statut en base est explicitement 'locked'
     * 2. Ou si son statut est 'submitted' et que la date limite (J+10) est dépassée
     *    sans qu'un déverrouillage récent de l'admin soit actif.
     */
    public function isLocked(): bool
    {
        if ($this->status === 'locked') {
            return true;
        }

        if ($this->status === 'submitted') {
            $deadline = $this->edit_deadline;
            if (! $deadline && $this->scheduledSession?->session_date) {
                $deadline = Carbon::parse($this->scheduledSession->session_date)->addDays(10);
            }

            if ($deadline) {
                $isPastDeadline = Carbon::now()->startOfDay()->greaterThan($deadline);
                $hasValidUnlock = $this->unlocked_at && $this->unlocked_at->isToday();

                return $isPastDeadline && ! $hasValidUnlock;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Mutateurs et Accesseurs de compatibilité API / Formulaire
    |--------------------------------------------------------------------------
    */

    public function getAdjustmentsMadeAttribute(): ?bool
    {
        return $this->adjustment_done;
    }

    public function setAdjustmentsMadeAttribute($value): void
    {
        $this->attributes['adjustment_done'] = $value;
    }

    public function getAdjustmentsReasonAttribute(): ?string
    {
        return $this->adjustment_detail;
    }

    public function setAdjustmentsReasonAttribute($value): void
    {
        $this->attributes['adjustment_detail'] = $value;
    }

    public function getNextSessionNotesAttribute(): ?string
    {
        return $this->next_steps;
    }

    public function setNextSessionNotesAttribute($value): void
    {
        $this->attributes['next_steps'] = $value;
    }

    public function getMaintainInClassAttribute(): ?bool
    {
        return $this->keep_in_class;
    }

    public function setMaintainInClassAttribute($value): void
    {
        $this->attributes['keep_in_class'] = $value;
    }

    public function getDiscardedPathsAttribute(): ?string
    {
        return $this->dropped_leads;
    }

    public function setDiscardedPathsAttribute($value): void
    {
        $this->attributes['dropped_leads'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Filtres de statut)
    |--------------------------------------------------------------------------
    */

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'locked']);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function scheduledSession(): BelongsTo
    {
        return $this->belongsTo(ScheduledSession::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function unlockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    /**
     * Évaluation qualitative des participants (Temps 4 de la fiche).
     */
    public function participantEvaluation(): HasOne
    {
        return $this->hasOne(ParticipantEvaluation::class);
    }

    public function participantEvaluations(): HasMany
    {
        return $this->hasMany(ParticipantEvaluation::class);
    }

    /**
     * Exercices menés pendant la séance (Temps 3 de la fiche, ordonnés par position).
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('position');
    }

    /**
     * Tous les artistes présents lors de l'atelier (P5 - Les artistes).
     */
    public function attendingArtists(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'logbook_entry_user')
            ->withPivot('member_role');
    }

    /**
     * Valeurs des champs personnalisés spécifiques à ce projet.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
