<?php

namespace App\Http\Requests\Logbook;

use App\Enums\UserRole;
use App\Models\LogbookEntry;
use App\Support\LogbookRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Modifier une fiche : son auteur seul, tant qu'elle n'est pas verrouillée et que
 * l'échéance (J+10) n'est pas passée. Une fiche déjà soumise doit rester complète.
 */
class UpdateLogbookEntryRequest extends FormRequest
{
    protected ?LogbookEntry $entry = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('entry');
        $this->entry = ctype_digit($id)
            ? LogbookEntry::query()->with(['scheduledSession.project', 'evaluation', 'exercises'])->find($id)
            : null;
        abort_if($this->entry === null, 404);

        $user = $this->user();
        if ($this->entry->author_id !== $user->id) {
            // L'administration voit la fiche, mais ne la modifie pas ; pour les autres, elle n'existe pas.
            abort_unless($user->hasRole(UserRole::Admin, UserRole::SuperUser), 404);
            abort(403, 'Seul·e l’auteur·rice de la fiche peut la modifier.');
        }
        abort_if($this->entry->status === 'locked' || $this->entry->deadlinePassed(), 403,
            'La fiche est verrouillée : le délai de 10 jours après la séance est passé.');
        abort_if($this->entry->scheduledSession->status === 'cancelled'
            || $this->entry->scheduledSession->project->status === 'archived', 403,
            'La séance a été annulée ou son projet archivé : la fiche ne se modifie plus.');

        return true;
    }

    public function entry(): LogbookEntry
    {
        return $this->entry;
    }

    public function rules(): array
    {
        return LogbookRules::draft($this->entry->scheduledSession);
    }

    /**
     * Saisie validée, plus une liste d'exercices vide si elle a été envoyée : Laravel retire
     * de validated() un tableau vide dont les éléments ont des règles.
     */
    public function payload(): array
    {
        $data = $this->validated();
        if ($this->has('exercises') && $this->input('exercises') === []) {
            $data['exercises'] = [];
        }

        return $data;
    }

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

    public function messages(): array
    {
        return LogbookRules::messages();
    }

    public function attributes(): array
    {
        return LogbookRules::attributes();
    }
}
