<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vues des statistiques. On les calcule à partir des fiches rendues : on ne stocke rien.
 */
return new class extends Migration
{
    /**
     * Les vues, dans l'ordre où on les supprime : celles qui dépendent des autres d'abord.
     *
     * @var list<string>
     */
    private array $views = [
        'v_missing_entries',
        'v_project_indicators',
        'v_project_participants',
        'v_sub_project_participants',
    ];

    /**
     * On applique la migration.
     */
    public function up(): void
    {
        $this->dropViews();

        // Les participants d'un sous-projet : le maximum des présents sur ses séances, et non
        // leur somme (retour de Mariette du 14/09).
        DB::statement("
            CREATE VIEW v_sub_project_participants AS
            SELECT sp.sub_project_id,
                   sp.project_id,
                   MAX(sp.attendees) AS participants,
                   COUNT(DISTINCT sp.scheduled_session_id) AS sessions_documented
            FROM scheduled_session_sub_project sp
            JOIN logbook_entries le ON le.scheduled_session_id = sp.scheduled_session_id
            WHERE le.status IN ('submitted', 'locked')
            GROUP BY sp.sub_project_id, sp.project_id
        ");

        // Les participants d'un projet : la somme des maximums de ses sous-projets.
        DB::statement('
            CREATE VIEW v_project_participants AS
            SELECT p.id AS project_id,
                   p.name,
                   COALESCE(SUM(v.participants), 0) AS participants
            FROM projects p
            LEFT JOIN v_sub_project_participants v ON v.project_id = p.id
            GROUP BY p.id, p.name
        ');

        // Les moyennes d'engagement, d'expression et d'autonomie, par projet.
        DB::statement("
            CREATE VIEW v_project_indicators AS
            SELECT s.project_id,
                   COUNT(*) AS entries,
                   ROUND(AVG(pe.engagement), 2) AS avg_engagement,
                   ROUND(AVG(pe.expression), 2) AS avg_expression,
                   ROUND(AVG(pe.autonomy), 2) AS avg_autonomy
            FROM logbook_entries le
            JOIN scheduled_sessions s ON s.id = le.scheduled_session_id
            JOIN participant_evaluations pe ON pe.logbook_entry_id = le.id
            WHERE le.status IN ('submitted', 'locked')
            GROUP BY s.project_id
        ");

        // Les séances passées dont la fiche n'a pas été rendue.
        DB::statement("
            CREATE VIEW v_missing_entries AS
            SELECT s.id AS scheduled_session_id, s.project_id, s.session_date, s.referent_id
            FROM scheduled_sessions s
            LEFT JOIN logbook_entries le
                   ON le.scheduled_session_id = s.id AND le.status IN ('submitted', 'locked')
            WHERE s.status <> 'cancelled'
              AND s.session_date < CURRENT_DATE
              AND le.id IS NULL
        ");
    }

    /**
     * On annule la migration.
     */
    public function down(): void
    {
        $this->dropViews();
    }

    /**
     * On supprime nos vues, si elles existent.
     */
    private function dropViews(): void
    {
        foreach ($this->views as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }
    }
};
