<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'postal_code',
        'city',
        'is_klap',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_klap' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
