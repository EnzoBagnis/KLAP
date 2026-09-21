<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Planning : on est le 21/09/2026 dans les données (séances passées, annulée, à venir)
class PlanningSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // id, projet, date, début, fin, lieu, référent, type, précision, statut
        $sessions = [
            [1,  1, '2026-09-08', '10:00', '12:00', 4, 3, 'practice_workshop', null, 'done'],
            [2,  1, '2026-09-10', '14:00', '16:00', 4, 3, 'practice_workshop', null, 'done'],
            [3,  1, '2026-09-12', '10:00', '12:00', 4, 3, 'practice_workshop', null, 'cancelled'],
            [4,  1, '2026-09-15', '10:00', '12:00', 2, 3, 'guided_tour', null, 'done'],
            [5,  1, '2026-09-17', '14:00', '16:00', 4, 3, 'practice_workshop', null, 'done'],
            [6,  1, '2026-09-18', '10:00', '12:00', 4, 3, 'viewing_workshop', null, 'done'],
            [7,  1, '2026-09-24', '10:00', '12:00', 4, 3, 'practice_workshop', null, 'planned'],
            [8,  1, '2026-10-01', '19:00', '20:30', 3, 3, 'performance', null, 'planned'],
            [9,  2, '2026-09-09', '13:30', '15:30', 3, 4, 'viewing_workshop', null, 'done'],
            [10, 2, '2026-09-16', '13:30', '15:30', 3, 4, 'practice_workshop', null, 'done'],
            [11, 2, '2026-09-29', '13:30', '15:30', 3, 4, 'practice_workshop', null, 'planned'],
            [12, 3, '2026-09-11', '18:00', '20:00', 1, 6, 'other', 'Rencontre avec les familles', 'done'],
            [13, 5, '2026-09-19', '10:00', '12:00', 5, 6, 'practice_workshop', null, 'done'],
            [14, 5, '2026-10-03', '10:00', '12:00', 5, 6, 'complementary_workshop', null, 'planned'],
        ];
        foreach ($sessions as [$id, $project, $date, $start, $end, $location, $referent, $type, $detail, $status]) {
            DB::table('scheduled_sessions')->insert([
                'id' => $id, 'project_id' => $project, 'session_date' => $date, 'start_time' => $start, 'end_time' => $end,
                'location_id' => $location, 'referent_id' => $referent, 'session_type' => $type,
                'session_type_detail' => $detail, 'status' => $status, 'created_by' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // séance 4 = transversale sur 2 groupes, séance 8 = spectacle pour tout le lycée
        $groups = [
            [1, 1, 1, 18], [2, 3, 1, 22], [3, 4, 1, null],
            [4, 1, 1, 17], [4, 2, 1, 19],
            [5, 3, 1, 24], [6, 2, 1, null], [7, 1, 1, null],
            [8, 1, 1, null], [8, 2, 1, null], [8, 3, 1, null], [8, 4, 1, null],
            [9, 5, 2, 25], [10, 6, 2, 23], [11, 7, 2, null],
            [12, 9, 3, 12], [13, 12, 5, 15], [14, 12, 5, null],
        ];
        foreach ($groups as [$session, $subProject, $project, $attendees]) {
            DB::table('scheduled_session_sub_project')->insert([
                'scheduled_session_id' => $session, 'sub_project_id' => $subProject,
                'project_id' => $project, 'attendees' => $attendees,
            ]);
        }
    }
}
