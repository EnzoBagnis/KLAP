<?php

namespace App\Http\Requests\Logbook;

use App\Models\LogbookEntry;
use App\Models\ScheduledSession;
use App\Support\LogbookRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Ouvrir la fiche d'une séance. Seul·e le ou la référent·e de la séance la remplit
 * (« l'intervenant qui remplit la fiche », schéma). Une fiche par séance au plus.
 */
class StoreLogbookEntryRequest extends FormRequest
{
    private ?ScheduledSession $scheduledSession = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('session');
        $this->scheduledSession = ctype_digit($id) ? ScheduledSession::query()->with('project')->find($id) : null;
        abort_if($this->scheduledSession === null, 404);
        abort_if($this->scheduledSession->referent_id !== $this->user()->id, 403,
            'Seul·e l’intervenant·e référent·e de la séance peut en remplir la fiche.');
        abort_if($this->scheduledSession->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche.');

        // Après J+10, la fiche ne pourrait plus être modifiée ni soumise : on ne l'ouvre pas.
        $deadline = $this->scheduledSession->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS)->toDateString();
        abort_if(LogbookEntry::localNow()->toDateString() > $deadline, 403,
            'Le délai de 10 jours après la séance est passé : la fiche ne peut plus être ouverte.');

        if ($this->scheduledSession->status === 'cancelled') {
            throw ValidationException::withMessages(['session' => 'Cette séance est annulée : elle n’a pas de fiche.']);
        }
        if ($this->scheduledSession->project->status === 'archived') {
            throw ValidationException::withMessages(['session' => 'Ce projet est archivé.']);
        }
        // La fiche rend compte d'une séance passée : elle s'ouvre à partir du jour même (heure de Paris).
        if ($this->scheduledSession->session_date->toDateString() > LogbookEntry::localNow()->toDateString()) {
            throw ValidationException::withMessages([
                'session' => 'La séance n’a pas encore eu lieu : la fiche se remplit à partir du jour de la séance.',
            ]);
        }

        return true;
    }

    public function scheduledSession(): ScheduledSession
    {
        return $this->scheduledSession;
    }

    public function rules(): array
    {
        return LogbookRules::draft($this->scheduledSession);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $state = LogbookRules::state(new LogbookEntry, $validator->validated());
                foreach (LogbookRules::draftErrors($state) as $key => $message) {
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
