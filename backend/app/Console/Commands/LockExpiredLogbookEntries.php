<?php

namespace App\Console\Commands;

use App\Models\LogbookEntry;
use Illuminate\Console\Command;

/**
 * On verrouille les fiches soumises dont l'échéance (séance + 10 jours, heure de Paris) est
 * passée. La commande tourne chaque nuit (routes/console.php). Un brouillon jamais rendu
 * reste `draft` (contrainte chk_entries_submitted) : deadlinePassed() le met déjà en lecture
 * seule, et on continue de le compter dans v_missing_entries.
 */
class LockExpiredLogbookEntries extends Command
{
    /** @var string le nom de la commande et ses arguments */
    protected $signature = 'logbook:lock-expired';

    /** @var string ce qu'affiche `php artisan list` */
    protected $description = 'Verrouille les fiches soumises dont le délai de modification est passé';

    /**
     * @return int le code de sortie de la commande
     */
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
