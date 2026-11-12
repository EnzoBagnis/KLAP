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
 * Ouvrir la fiche d'une séance : le ou la référent·e, ou l'administration à sa place
 * (droits emboîtés du cahier des charges, #141). La fiche reste celle du ou de la référent·e.
 * On n'accepte qu'une fiche par séance.
 */
class StoreLogbookEntryRequest extends FormRequest
{
    /** La séance de l'URL, qu'on charge dans authorize(). */
    private ?ScheduledSession $scheduledSession = null;

    /**
     * On vérifie, dans l'ordre : que la séance existe (404), que la personne a le droit
     * d'ouvrir sa fiche (403), qu'il n'y en a pas déjà une (409), que le délai n'est pas passé
     * (403), puis que la séance peut recevoir une fiche (422).
     *
     * @throws ValidationException si la séance ne peut pas recevoir de fiche
     */
    public function authorize(): bool
    {
        $id = (string) $this->route('session');
        $this->scheduledSession = ctype_digit($id) ? ScheduledSession::query()->with(['project', 'referent'])->find($id) : null;
        abort_if($this->scheduledSession === null, 404);
        // Le ou la référent·e remplit sa fiche ; l'administration peut l'ouvrir à sa place (#141).
        abort_unless($this->scheduledSession->referent_id === $this->user()->id
            || $this->user()->hasRole(UserRole::Admin, UserRole::SuperUser), 403,
            'Seul·e l’intervenant·e référent·e de la séance, l’administratrice ou la super-utilisatrice peut ouvrir sa fiche.');
        abort_if($this->scheduledSession->logbookEntry()->exists(), 409, 'Cette séance a déjà une fiche.');

        // Après J+10, on ne pourrait plus ni modifier ni soumettre la fiche : on ne l'ouvre pas.
        $deadline = $this->scheduledSession->session_date->copy()->addDays(LogbookEntry::EDIT_DAYS)->toDateString();
        abort_if(LogbookEntry::localNow()->toDateString() > $deadline, 403,
            'Le délai de 10 jours après la séance est passé : la fiche ne peut plus être ouverte.');

        if (! $this->scheduledSession->referent->is_active) {
            throw ValidationException::withMessages(['session' => 'L’intervenant·e référent·e de cette séance n’a plus de compte actif.']);
        }
        if ($this->scheduledSession->status === 'cancelled') {
            throw ValidationException::withMessages(['session' => 'Cette séance est annulée : elle n’a pas de fiche.']);
        }
        if ($this->scheduledSession->project->status === 'archived') {
            throw ValidationException::withMessages(['session' => 'Ce projet est archivé.']);
        }
        // La fiche rend compte d'une séance passée : on l'ouvre à partir du jour même (heure de Paris).
        if ($this->scheduledSession->session_date->toDateString() > LogbookEntry::localNow()->toDateString()) {
            throw ValidationException::withMessages([
                'session' => 'La séance n’a pas encore eu lieu : la fiche se remplit à partir du jour de la séance.',
            ]);
        }

        return true;
    }

    /**
     * La séance dont on ouvre la fiche.
     */
    public function scheduledSession(): ScheduledSession
    {
        return $this->scheduledSession;
    }

    /**
     * Les règles qu'on applique à la saisie.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return LogbookRules::draft($this->scheduledSession);
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
                $state = LogbookRules::state(new LogbookEntry, $validator->validated());
                foreach (LogbookRules::draftErrors($state) as $key => $message) {
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
