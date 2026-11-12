<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un exercice mené pendant la séance (section 3 de la fiche). On garde l'ordre de saisie
 * dans `position`.
 *
 * @property int $id
 * @property int $logbook_entry_id
 * @property int $position
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Exercise extends Model
{
    /** @var list<string> */
    protected $fillable = ['position', 'label'];
}
