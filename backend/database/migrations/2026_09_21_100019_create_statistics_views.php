<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Vues pour les stats, calculées à partir des fiches rendues (rien n'est stocké)
return new class extends Migration
{
    private array $views = [
        'v_missing_entries',
        'v_project_indicators',
        'v_project_participants',
        'v_sub_project_participants',
    ];

    public function up(): void
    {
        $this->dropViews();

        // participants d'un sous-projet = max des présents sur ses séances (pas la somme, cf. retour de Mariette)
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

        // participants d'un projet = somme des max de chaque sous-projet
        DB::statement('
            CREATE VIEW v_project_participants AS
            SELECT p.id AS project_id,
                   p.name,
                   COALESCE(SUM(v.participants), 0) AS participants
            FROM projects p
            LEFT JOIN v_sub_project_participants v ON v.project_id = p.id
            GROUP BY p.id, p.name
        ');

        // moyennes engagement / expression / autonomie par projet
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

        // séances passées dont la fiche n'a pas été rendue
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

    public function down(): void
    {
        $this->dropViews();
    }

    private function dropViews(): void
    {
        foreach ($this->views as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }
    }
};
