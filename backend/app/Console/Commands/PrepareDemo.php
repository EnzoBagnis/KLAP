<?php

namespace App\Console\Commands;

use App\Models\KlapNotification;
use App\Models\LogbookEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Données de démonstration datées du jour de la démo (soutenance, rendez-vous cliente).
 *
 * Notre jeu de données décrit la situation au 21/09/2026 : fiches rendues, verrouillées, en
 * brouillon, demande de réouverture en attente. La commande recrée la base, décale toutes
 * ces dates pour que le 21/09/2026 devienne le jour choisi (les états restent les mêmes),
 * puis ajoute une séance de Léa Fournier ce jour-là, sans fiche : c'est elle qu'on remplit
 * en direct sur le téléphone (effet waouh, étape 1).
 *
 *   php artisan klap:demo                     (jour de la démo : aujourd'hui, heure de Paris)
 *   php artisan klap:demo --date=2026-11-19
 *
 * ELLE EFFACE TOUTE LA BASE : on demande une confirmation, sauf avec --force, et on refuse
 * de la lancer en production.
 */
class PrepareDemo extends Command
{
    /** @var string le nom de la commande et ses arguments */
    protected $signature = 'klap:demo {--date= : Jour de la démo (AAAA-MM-JJ), aujourd\'hui par défaut} {--force : Ne pas demander de confirmation}';

    /** @var string ce qu'affiche `php artisan list` */
    protected $description = 'Recrée la base avec les données de démonstration, datées du jour de la démo';

    /** Le jour que décrit le jeu de données (DatabaseSeeder). */
    public const REFERENCE_DAY = '2026-09-21';

    /** Les tables techniques, ou dont les dates ne racontent rien : on ne les décale pas. */
    private const SKIPPED_TABLES = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'personal_access_tokens'];

    /**
     * Les dates qu'on ne décale pas : un projet garde son année scolaire (septembre à juin),
     * et les séances décalées y restent comprises.
     */
    private const FIXED_COLUMNS = ['projects.start_date', 'projects.end_date', 'project_user.start_date', 'project_user.end_date'];

    /**
     * On recrée la base, on décale les dates, puis on ajoute la séance du jour.
     *
     * @return int le code de sortie de la commande
     */
    public function handle(): int
    {
        // Notre garde-fou (#255) : la démonstration efface toute la base, on la refuse en production.
        if (app()->isProduction()) {
            $this->error('klap:demo efface toute la base : refusé en production (APP_ENV=production). Pour le premier compte, utilisez klap:admin.');

            return self::FAILURE;
        }

        $day = $this->option('date') ?: LogbookEntry::localNow()->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || ! strtotime($day)) {
            $this->error('La date doit être au format AAAA-MM-JJ.');

            return self::INVALID;
        }

        $database = DB::connection()->getDatabaseName();
        if (! $this->option('force') && ! $this->confirm("Toute la base « {$database} » va être effacée puis remplie avec les données de démonstration. Continuer ?")) {
            $this->info('Rien n’a été modifié.');

            return self::FAILURE;
        }

        $seededAt = now();
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $days = (int) Carbon::parse(self::REFERENCE_DAY)->diffInDays(Carbon::parse($day), false);
        DB::transaction(function () use ($days, $seededAt) {
            $this->shiftDates($days, $seededAt);
            $this->rewriteNotificationMessages();
            $this->addTodaySession();
        });
        // On remet les états en accord avec la nouvelle date (verrouillage à J+10).
        $this->call('logbook:lock-expired');

        $this->newLine();
        $this->info("Données de démonstration prêtes pour le {$day} (dates décalées de {$days} jours).");
        $this->line('Comptes (mot de passe Klap2026!) : mariette.travard@klap.demo (admin), emilie.garetier@klap.demo (super-user), lea.fournier@klap.demo (intervenante).');
        [$start, $end] = $this->todaySlot();
        $this->line("Séance du jour sans fiche : Léa Fournier, Arts Danse au Lycée Charles, Terminales spécialité danse, {$start} - {$end}.");
        $this->line('La fiche se soumet après la fin de la séance : relancez la commande si la démo a lieu avant.');

        return self::SUCCESS;
    }

    /**
     * On décale de $days jours chaque date écrite en dur dans le jeu de données. Les valeurs
     * posées à l'instant du remplissage (created_at = now()) restent telles quelles.
     *
     * @param  int  $days  le nombre de jours, négatif pour reculer
     * @param  Carbon  $seededAt  l'instant où on a lancé le remplissage
     */
    private function shiftDates(int $days, Carbon $seededAt): void
    {
        if ($days === 0) {
            return;
        }
        // On se limite au schéma courant : sous MariaDB, getTables() liste aussi les autres
        // bases visibles.
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            $name = $table['name'];
            if (in_array($name, self::SKIPPED_TABLES, true) || str_starts_with($name, 'sqlite_')) {
                continue;
            }
            $columns = collect(Schema::getColumns($name))
                ->filter(fn ($column) => preg_match('/^(date|datetime|timestamp)/i', $column['type_name']))
                ->reject(fn ($column) => in_array("{$name}.{$column['name']}", self::FIXED_COLUMNS, true))
                ->mapWithKeys(fn ($column) => [$column['name'] => strtolower($column['type_name']) === 'date'])
                ->all();
            if ($columns === []) {
                continue;
            }
            $keys = Schema::hasColumn($name, 'id') ? ['id'] : ['project_id', 'user_id'];
            foreach (DB::table($name)->get([...$keys, ...array_keys($columns)]) as $row) {
                $changes = [];
                foreach ($columns as $column => $isDate) {
                    $value = $row->{$column};
                    if ($value === null) {
                        continue;
                    }
                    $moment = Carbon::parse($value);
                    if (! $isDate && abs($moment->diffInSeconds($seededAt)) < 120) {
                        continue; // une valeur posée par now() au remplissage : on n'y touche pas
                    }
                    $shifted = $moment->addDays($days);
                    $changes[$column] = $isDate ? $shifted->toDateString() : $shifted->toDateTimeString();
                }
                if ($changes !== []) {
                    DB::table($name)->where(array_intersect_key((array) $row, array_flip($keys)))->update($changes);
                }
            }
        }
    }

    /**
     * Les messages citent la date de la séance en toutes lettres : on les réécrit après le
     * décalage, au format de l'application, pour que la cloche dise la même chose que le planning.
     */
    private function rewriteNotificationMessages(): void
    {
        $notifications = KlapNotification::query()
            ->whereIn('type', array_keys(KlapNotification::MESSAGES))
            ->whereNotNull('logbook_entry_id')
            ->with('logbookEntry.author')
            ->get();
        foreach ($notifications as $notification) {
            $entry = $notification->logbookEntry;
            if ($entry?->author !== null) {
                // On passe par une requête directe, pour ne pas toucher aux dates de la notification.
                DB::table('klap_notifications')->where('id', $notification->id)
                    ->update(['message' => KlapNotification::messageFor($notification->type, $entry->author->name, $entry)]);
            }
        }
    }

    /**
     * On ajoute la séance du jour de Léa, sans fiche : c'est celle qu'on remplit en direct
     * pendant la démo.
     */
    private function addTodaySession(): void
    {
        $project = DB::table('projects')->where('name', 'Arts Danse au Lycée Charles')->first();
        $lea = DB::table('users')->where('email', 'lea.fournier@klap.demo')->first();
        $group = DB::table('sub_projects')->where('project_id', $project->id)->where('name', 'Terminales spécialité danse')->first();

        $id = DB::table('scheduled_sessions')->insertGetId([
            'project_id' => $project->id,
            'session_date' => $this->demoDay(),
            'start_time' => $this->todaySlot()[0],
            'end_time' => $this->todaySlot()[1],
            'location_id' => $project->default_location_id,
            'referent_id' => $lea->id,
            'session_type' => 'practice_workshop',
            'status' => 'planned',
            'created_by' => DB::table('users')->where('email', 'mariette.travard@klap.demo')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('scheduled_session_sub_project')->insert([
            'scheduled_session_id' => $id, 'sub_project_id' => $group->id, 'project_id' => $project->id, 'attendees' => null,
        ]);
    }

    /**
     * L'horaire de la séance du jour (#182). Une fiche ne se soumet qu'après la fin de la
     * séance : la séance doit donc être terminée au moment de la démo. Si on lance la commande
     * l'après-midi, on garde 10 h - 12 h ; avant midi, la séance se termine au quart d'heure qui
     * précède. Pour une démo un autre jour (--date), on garde 10 h - 12 h.
     *
     * @return array{0: string, 1: string} le début et la fin, au format « HH:MM »
     */
    private function todaySlot(): array
    {
        $now = LogbookEntry::localNow();
        if ($this->option('date') && $this->option('date') !== $now->toDateString()) {
            return ['10:00', '12:00'];
        }
        $end = $now->copy()->setTime($now->hour, intdiv($now->minute, 15) * 15);
        if ($end->format('H:i') >= '12:00') {
            return ['10:00', '12:00'];
        }
        // On garde au moins 15 minutes de séance, sans remonter avant minuit.
        $end = $end->max($now->copy()->setTime(0, 15));
        $start = $end->copy()->subHours(2)->max($now->copy()->startOfDay());

        return [$start->format('H:i'), $end->format('H:i')];
    }

    /**
     * Le jour de la démo : celui de --date, sinon aujourd'hui à l'heure de Paris.
     */
    private function demoDay(): string
    {
        return $this->option('date') ?: LogbookEntry::localNow()->toDateString();
    }
}
