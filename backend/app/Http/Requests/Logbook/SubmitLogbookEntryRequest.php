<?php

namespace App\Http\Requests\Logbook;

/** Soumettre une fiche : mêmes droits que la modification, sans saisie (la fiche enregistrée est vérifiée). */
class SubmitLogbookEntryRequest extends UpdateLogbookEntryRequest
{
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [];
    }
}
