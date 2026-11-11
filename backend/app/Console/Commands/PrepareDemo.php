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
 * Le jeu de données décrit la situation au 21/09/2026 : fiches rendues, verrouillées, en
 * brouillon, demande de réouverture en attente. La commande recrée la base, décale toutes
 * ces dates pour que le 21/09/2026 devienne le jour choisi (les états restent les mêmes),
 * puis ajoute une séance de Léa Fournier ce jour-là, sans fiche : c'est elle qu'on remplit
 * en direct sur le téléphone (effet waouh, étape 1).
 *
 *   php artisan klap:demo                     (jour de la démo : aujourd'hui, heure de Paris)
 *   php artisan klap:demo --date=2026-11-19
 *
 * EFFACE TOUTE LA BASE : confirmation demandée, sauf avec --force.
 */
class PrepareDemo extends Command
{
    protected $signature = 'klap:demo {--date= : Jour de la démo (AAAA-MM-JJ), aujourd\'hui par défaut} {--force : Ne pas demander de confirmation}';

    protected $description = 'Recrée la base avec les données de démonstration, datées du jour de la démo';

    /** Jour décrit par le jeu de données (DatabaseSeeder). */
    public const REFERENCE_DAY = '2026-09-21';

    /** Tables techniques, ou dont les dates ne racontent rien. */
    private const SKIPPED_TABLES = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'personal_access_tokens'];

    /**
     * Dates qui ne bougent pas : un projet garde son année scolaire (septembre à juin), et les
     * séances décalées y restent comprises.
     */
    private const FIXED_COLUMNS = ['projects.start_date', 'projects.end_date', 'project_user.start_date', 'project_user.end_date'];

    public function handle(): int
    {
        // Garde-fou (#255) : la démonstration efface toute la base, jamais en production.
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
        // États cohérents avec la nouvelle date (verrouillage à J+10).
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
     * Décale de $days jours chaque date écrite en dur dans le jeu de données. Les valeurs posées
     * à l'instant du remplissage (created_at = now()) restent telles quelles.
     */
    private function shiftDates(int $days, Carbon $seededAt): void
    {
        if ($days === 0) {
            return;
        }
        // Schéma courant seulement : sous MariaDB, getTables() liste aussi les autres bases visibles.
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
                        continue; // posé par now() au remplissage
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
     * Les messages citent la date de la séance en toutes lettres : réécrits après le décalage,
     * au format de l'application, pour que la cloche dise la même chose que le planning.
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
                // Requête directe : les dates de la notification ne changent pas.
                DB::table('klap_notifications')->where('id', $notification->id)
                    ->update(['message' => KlapNotification::messageFor($notification->type, $entry->author->name, $entry)]);
            }
        }
    }

    /** Séance du jour de Léa, sans fiche : remplie en direct pendant la démo. */
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
     * Horaire de la séance du jour (#182). Une fiche ne se soumet qu'après la fin de la séance :
     * la séance doit donc être terminée au moment de la démo. Lancée l'après-midi, la commande
     * garde 10 h - 12 h ; lancée avant midi, la séance se termine au quart d'heure qui précède.
     * Pour une démo un autre jour (--date), on garde 10 h - 12 h.
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
        // Au moins 15 minutes de séance, sans passer avant minuit.
        $end = $end->max($now->copy()->setTime(0, 15));
        $start = $end->copy()->subHours(2)->max($now->copy()->startOfDay());

        return [$start->format('H:i'), $end->format('H:i')];
    }

    private function demoDay(): string
    {
        return $this->option('date') ?: LogbookEntry::localNow()->toDateString();
    }
}
