<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lieu d'une séance : un studio de KLAP ou un lieu partenaire. Tout le monde peut en
 * ajouter un (projets en tournée) ; on ne le supprime jamais, on le désactive.
 *
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string $postal_code
 * @property string $city
 * @property bool $is_klap
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'address',
        'postal_code',
        'city',
        'is_klap',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_klap' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
