<?php

namespace App\Http\Requests\Logbook;

use App\Enums\UserRole;
use App\Models\LogbookEntry;
use App\Models\ScheduledSession;
use App\Support\LogbookRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Ouvrir la fiche d'une séance : le ou la référent·e, ou l'administration pour lui ou elle
 * (droits emboîtés du cahier des charges, #141). La fiche reste celle du ou de la référent·e.
 * Une fiche par séance au plus.
 */
class StoreLogbookEntryRequest extends FormRequest
{
    private ?ScheduledSession $scheduledSession = null;

    public function authorize(): bool
    {
        $id = (string) $this->route('session');
        $this->scheduledSession = ctype_digit($id) ? ScheduledSession::query()->with(['project', 'referent'])->find($id) : null;
        abort_if($this->scheduledSession === null, 404);
        // Le ou la référent·e remplit sa fiche ; l'administration peut l'ouvrir pour lui ou elle (#141).
        abort_unless($this->scheduledSession->referent_id === $this->user()->id
            || $this->user()->hasRole(UserRole::Admin, UserRole::SuperUser), 403,
            'Seul·e l’intervenant·e référent·e de la séance, l’administratrice ou la super-utilisatrice peut ouvrir sa fiche.');
        abort_if($this->scheduledSession->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche.');

        // Après J+10, la fiche ne pourrait plus être modifiée ni soumise : on ne l'ouvre pas.
        $deadline = $this->scheduledSession->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS)->toDateString();
        abort_if(LogbookEntry::localNow()->toDateString() > $deadline, 403,
            'Le délai de 10 jours après la séance est passé : la fiche ne peut plus être ouverte.');

        if (! $this->scheduledSession->referent?->is_active) {
            throw ValidationException::withMessages(['session' => 'L’intervenant·e référent·e de cette séance n’a plus de compte actif.']);
        }
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
