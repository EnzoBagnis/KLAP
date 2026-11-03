<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valeur saisie par un intervenant pour un champ personnalisé
 * sur une fiche de carnet de bord donnée.
 */
class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'logbook_entry_id', // Fiche de séance concernée
        'custom_field_id',  // Champ personnalisé auquel correspond cette réponse
        'value',            // Réponse saisie par l'intervenant
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function logbookEntry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
