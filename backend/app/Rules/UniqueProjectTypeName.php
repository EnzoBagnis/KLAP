<?php

namespace App\Rules;

use App\Models\ProjectType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nom de type de projet unique sans tenir compte de la casse (« résidence » et
 * « Résidence » sont le même type), sur SQLite comme sur MariaDB. L'index unique
 * de la base reste le dernier filet.
 */
class UniqueProjectTypeName implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = ProjectType::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('Ce type de projet existe déjà.');
        }
    }
}
