<?php

namespace App\Console\Commands;

use App\Models\LogbookEntry;
use Illuminate\Console\Command;

/**
 * Verrouille les fiches soumises dont l'échéance (séance + 10 jours, heure de Paris) est passée.
 * Un brouillon jamais rendu reste `draft` (contrainte chk_entries_submitted) : il est déjà
 * en lecture seule par deadlinePassed() et reste compté dans v_missing_entries.
 */
class LockExpiredLogbookEntries extends Command
{
    protected $signature = 'logbook:lock-expired';

    protected $description = 'Verrouille les fiches soumises dont le délai de modification est passé';

    public function handle(): int
    {
        $count = LogbookEntry::query()
            ->where('status', 'submitted')
            ->whereDate('edit_deadline', '<', LogbookEntry::localNow()->toDateString())
            ->update(['status' => 'locked', 'updated_at' => now()]);

        $this->info("{$count} fiche(s) verrouillée(s).");

        return self::SUCCESS;
    }
}
