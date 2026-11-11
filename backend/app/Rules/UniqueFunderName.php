<?php

namespace App\Rules;

use App\Models\Funder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nom de financeur unique sans tenir compte de la casse (« Région Sud » et « région sud »),
 * sur SQLite comme sur MariaDB. L'index unique de la base reste le dernier filet.
 */
class UniqueFunderName implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Funder::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('Ce financeur existe déjà.');
        }
    }
}
