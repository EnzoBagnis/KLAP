<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Les 5 vrais projets + un projet archivé pour tester les filtres
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $projects = [
            [1, 'Arts Danse au Lycée Charles', 'Option et spécialité danse, ateliers + visites + spectacles à KLAP', '2026-09-01', '2027-06-30', 'active', 4, null, 1, 50],
            [2, 'Approche de la création chorégraphique : autour de BRAISES', '4 collèges, parcours autour de la pièce BRAISES', '2026-09-01', '2027-05-31', 'active', 3, null, 2, 32],
            [3, 'AIMER', null, '2026-09-01', '2027-06-30', 'active', 1, null, 3, 30],
            [4, 'Classes cousines', '2 classes jumelées sur un même parcours', '2026-10-01', '2027-04-30', 'draft', 2, null, 4, 10],
            [5, 'Étincelles', 'création en territoire 2026', '2026-09-01', '2026-12-31', 'active', 5, null, 5, 30],
            [6, 'Projet pilote 2025', "saison dernière, gardé pour l'historique", '2025-01-06', '2025-06-27', 'archived', 2, '2025-07-15 10:00:00', 1, null],
        ];
        foreach ($projects as [$id, $name, $desc, $start, $end, $status, $location, $archivedAt, $type, $planned]) {
            DB::table('projects')->insert([
                'id' => $id, 'name' => $name, 'description' => $desc, 'start_date' => $start, 'end_date' => $end,
                'status' => $status, 'default_location_id' => $location, 'created_by' => 1,
                'archived_at' => $archivedAt, 'project_type_id' => $type, 'planned_sessions' => $planned, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([[1, 1], [1, 3], [2, 1], [2, 2], [3, 4], [4, 2], [5, 5], [5, 2], [6, 1]] as [$project, $funder]) {
            DB::table('funder_project')->insert(['project_id' => $project, 'funder_id' => $funder]);
        }

        $lycee = 'Lycée Saint-Charles';
        $subProjects = [
            [1,  1, 'Terminales spécialité danse',          $lycee, 'Terminale spé.',   '17-18 ans', 'Marseille', '13003', 20],
            [2,  1, 'Premières spécialité danse',           $lycee, 'Première spé.',    '16-17 ans', 'Marseille', '13003', 19],
            [3,  1, 'Secondes option danse',                $lycee, 'Seconde option',   '15-16 ans', 'Marseille', '13003', 24],
            [4,  1, 'Premières et Terminales option danse', $lycee, '1re / Tle option', '16-18 ans', 'Marseille', '13003', 22],
            [5,  2, 'Collège Aragon',        'Collège Aragon',        '4e', '13-14 ans', 'Marseille', null, 26],
            [6,  2, 'Collège Brassens',      'Collège Brassens',      '4e', '13-14 ans', 'Marseille', null, 25],
            [7,  2, "Collège de L'Estaque",  "Collège de L'Estaque",  '3e', '14-15 ans', 'Marseille', null, 24],
            [8,  2, 'Collège Izzo',          'Collège Izzo',          '4e', '13-14 ans', 'Marseille', null, 27],
            [9,  3, 'AIMER',              null,           'Groupe familles', 'tous âges', 'Marseille', '13003', 15],
            [10, 4, 'Classe cousine A',   'École (démo)', 'CM2',             '10-11 ans', 'Marseille', '13003', 25],
            [11, 4, 'Classe cousine B',   'École (démo)', 'CM2',             '10-11 ans', 'Aubagne',   '13400', 23],
            [12, 5, 'Étincelles',         null,           'Groupe unique',   '8-12 ans',  'Aubagne',   '13400', 18],
            [13, 6, 'Projet pilote 2025', null,           'Groupe unique',   '15-18 ans', 'Marseille', '13003', 16],
        ];
        foreach ($subProjects as [$id, $project, $name, $org, $group, $age, $city, $cp, $headcount]) {
            DB::table('sub_projects')->insert([
                'id' => $id, 'project_id' => $project, 'name' => $name, 'organization' => $org,
                'class_group' => $group, 'age_range' => $age, 'city' => $city, 'postal_code' => $cp,
                'expected_headcount' => $headcount, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // intervenants principaux (lead) et ponctuels (occasional)
        $members = [[1, 3, 'lead'], [1, 5, 'occasional'], [2, 4, 'lead'], [2, 5, 'occasional'],
            [3, 6, 'lead'], [4, 3, 'lead'], [5, 6, 'lead'], [5, 4, 'occasional'], [6, 7, 'lead']];
        foreach ($members as [$project, $user, $role]) {
            DB::table('project_user')->insert(['project_id' => $project, 'user_id' => $user, 'member_role' => $role]);
        }

        // champ 2 désactivé le 14/09, ses anciennes valeurs restent
        DB::table('custom_fields')->insert([
            ['id' => 1, 'project_id' => 1, 'section' => 'session', 'label' => 'Lien avec le programme de spécialité', 'field_type' => 'text',
                'options' => null, 'is_required' => false, 'position' => 1, 'is_active' => true,
                'activated_at' => '2026-09-01 10:00:00', 'deactivated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'project_id' => 1, 'section' => 'participants', 'label' => 'Tenue adaptée', 'field_type' => 'checkbox',
                'options' => null, 'is_required' => false, 'position' => 2, 'is_active' => false,
                'activated_at' => '2026-09-01 10:00:00', 'deactivated_at' => '2026-09-14 16:00:00', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'project_id' => 2, 'section' => 'participants', 'label' => "Niveau d'attention", 'field_type' => 'scale',
                'options' => json_encode(['min' => 1, 'max' => 5]), 'is_required' => false, 'position' => 1, 'is_active' => true,
                'activated_at' => '2026-09-01 10:00:00', 'deactivated_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
