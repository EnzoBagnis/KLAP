<?php

namespace App\Console\Commands;

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

    public function handle(): int
    {
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
            $this->addTodaySession();
        });
        // États cohérents avec la nouvelle date (verrouillage à J+10).
        $this->call('logbook:lock-expired');

        $this->newLine();
        $this->info("Données de démonstration prêtes pour le {$day} (dates décalées de {$days} jours).");
        $this->line('Comptes (mot de passe Klap2026!) : mariette.travard@klap.demo (admin), emilie.garetier@klap.demo (super-user), lea.fournier@klap.demo (intervenante).');
        $this->line('Séance du jour sans fiche : Léa Fournier, Arts Danse au Lycée Charles, Terminales spécialité danse, 10 h - 12 h.');

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

    /** Séance du jour de Léa, sans fiche : remplie en direct pendant la démo. */
    private function addTodaySession(): void
    {
        $project = DB::table('projects')->where('name', 'Arts Danse au Lycée Charles')->first();
        $lea = DB::table('users')->where('email', 'lea.fournier@klap.demo')->first();
        $group = DB::table('sub_projects')->where('project_id', $project->id)->where('name', 'Terminales spécialité danse')->first();

        $id = DB::table('scheduled_sessions')->insertGetId([
            'project_id' => $project->id,
            'session_date' => $this->demoDay(),
            'start_time' => '10:00',
            'end_time' => '12:00',
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

    private function demoDay(): string
    {
        return $this->option('date') ?: LogbookEntry::localNow()->toDateString();
    }
}
