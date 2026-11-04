<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organization',
        'class_group',
        'age_range',
        'city',
        'postal_code',
        'expected_headcount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expected_headcount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
