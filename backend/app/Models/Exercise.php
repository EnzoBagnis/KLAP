<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exercices chorégraphiques menés pendant la séance (Temps 3 de la fiche).
 * Une fiche contient entre 1 et 10 exercices maximum.
 * Chaque exercice possède une position ordonnée (1er exercice, 2e, etc.)
 * et un descriptif de 200 caractères maximum.
 */
class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'logbook_entry_id', // Fiche de séance à laquelle appartient cet exercice
        'position',         // Ordre chronologique de l'exercice (entre 1 et 10)
        'label',            // Descriptif de l'exercice mené (max 200 car)
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
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
