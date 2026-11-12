<?php

namespace App\Rules;

use App\Models\Funder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Nom de financeur unique sans tenir compte de la casse (« Région Sud » et « région sud »),
 * sur SQLite comme sur MariaDB. L'index unique de la base reste notre dernier filet.
 */
class UniqueFunderName implements ValidationRule
{
    /**
     * @param  int|null  $ignoreId  l'élément qu'on modifie, dont le propre nom n'est pas un doublon
     */
    public function __construct(private ?int $ignoreId = null) {}

    /**
     * On refuse le nom si un autre financeur le porte déjà, à la casse près.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
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
