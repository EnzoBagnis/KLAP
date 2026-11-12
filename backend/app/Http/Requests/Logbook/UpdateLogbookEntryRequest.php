<?php

namespace App\Http\Requests\Logbook;

use App\Models\LogbookEntry;
use App\Support\LogbookRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Modifier une fiche : son auteur, l'administratrice ou la super-utilisatrice (#141), tant
 * qu'elle n'est pas verrouillée et que l'échéance (J+10) n'est pas passée. On exige qu'une
 * fiche déjà soumise reste complète. Les collègues du projet la lisent sans la modifier.
 */
class UpdateLogbookEntryRequest extends FormRequest
{
    /** La fiche de l'URL, qu'on charge dans authorize(). */
    protected ?LogbookEntry $entry = null;

    /**
     * On vérifie que la fiche existe (404), que la personne peut la modifier (403), puis que
     * la fiche n'est ni verrouillée ni figée par une annulation ou un archivage (403).
     */
    public function authorize(): bool
    {
        $id = (string) $this->route('entry');
        $this->entry = ctype_digit($id)
            ? LogbookEntry::query()->with(['scheduledSession.project', 'evaluation', 'exercises'])->find($id)
            : null;
        abort_if($this->entry === null, 404);

        $user = $this->user();
        if (! $this->entry->canBeEditedBy($user)) {
            // La fiche d'un collègue du même projet se lit mais ne se modifie pas : on répond
            // 403. Pour toute autre fiche, on répond 404, comme si elle n'existait pas.
            abort_unless(LogbookEntry::query()->readableBy($user)->whereKey($this->entry->id)->exists(), 404);
            abort(403, 'Seul·e l’auteur·rice de la fiche, l’administratrice ou la super-utilisatrice peut la modifier.');
        }
        abort_if($this->entry->isLocked(), 403,
            'La fiche est verrouillée : le délai de 10 jours après la séance est passé.');
        abort_if($this->entry->scheduledSession->status === 'cancelled'
            || $this->entry->scheduledSession->project->status === 'archived', 403,
            'La séance a été annulée ou son projet archivé : la fiche ne se modifie plus.');

        return true;
    }

    /**
     * La fiche qu'on modifie.
     */
    public function entry(): LogbookEntry
    {
        return $this->entry;
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return LogbookRules::draft($this->entry->scheduledSession);
    }

    /**
     * La saisie validée. On y remet une liste d'exercices vide si elle a été envoyée : Laravel
     * retire de validated() un tableau vide dont les éléments ont des règles, et on ne saurait
     * plus qu'il faut effacer les exercices.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        if ($this->has('exercises') && $this->input('exercises') === []) {
            $data['exercises'] = [];
        }

        return $data;
    }

    /**
     * Les contrôles qu'on fait après les règles : ceux qui croisent plusieurs champs ou qui
     * lisent la base.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $state = LogbookRules::state($this->entry, $this->payload());
                $errors = LogbookRules::draftErrors($state);
                if ($this->entry->status === 'submitted') {
                    $errors += LogbookRules::submissionErrors($state, $this->entry->scheduledSession);
                }
                foreach ($errors as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }

    /**
     * Les messages d'erreur, en français : le front les affiche tels quels.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return LogbookRules::messages();
    }

    /**
     * Les noms des champs, tels qu'on les écrit dans les messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return LogbookRules::attributes();
    }
}
