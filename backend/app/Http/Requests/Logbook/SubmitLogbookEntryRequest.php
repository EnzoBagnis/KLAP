<?php

namespace App\Http\Requests\Logbook;

/**
 * Soumettre une fiche : on applique les mêmes droits que pour la modification, sans aucune
 * saisie. C'est la fiche enregistrée que le contrôleur vérifie.
 */
class SubmitLogbookEntryRequest extends UpdateLogbookEntryRequest
{
    /**
     * On n'attend aucune saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * On ne contrôle rien ici : le contrôleur vérifie que la fiche enregistrée est complète.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [];
    }
}
