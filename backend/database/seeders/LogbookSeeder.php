<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fiches de démonstration. Mariette a rouvert la 2, la 4 est un brouillon. On n'a pas donné
 * de fiche à la séance 6 : elle doit remonter dans v_missing_entries.
 */
class LogbookSeeder extends Seeder
{
    /**
     * On remplit les tables.
     */
    public function run(): void
    {
        $entries = [
            [
                'id' => 1, 'scheduled_session_id' => 1, 'author_id' => 3, 'status' => 'locked',
                'objectives' => 'Poser un cadre de confiance, premiers pas en impro guidée',
                'adjustment_done' => false, 'adjustment_detail' => null,
                'highlights' => "un élève très discret a proposé la phrase de fin et tout le groupe l'a reprise, beau moment",
                'tools_resources' => 'enceinte + playlist percu', 'learnings' => "écoute, occuper l'espace",
                'artist_review' => 'groupe réceptif, un peu de trac (rentrée)',
                'next_steps' => 'continuer les duos en miroir', 'dropped_leads' => null, 'keep_in_class' => true,
                'additional_notes' => null, 'edit_deadline' => '2026-09-18', 'submitted_at' => '2026-09-08 18:20:00',
            ],
            [
                'id' => 2, 'scheduled_session_id' => 2, 'author_id' => 3, 'status' => 'submitted',
                'objectives' => 'Ancrage au sol, respiration',
                'adjustment_done' => true,
                'adjustment_detail' => "Salle occupée jusqu'à 14h15, j'ai raccourci l'échauffement et on est passés direct au sol",
                'highlights' => 'Rien',
                'tools_resources' => null, 'learnings' => 'placement du bassin, relâchement',
                'artist_review' => 'séance dense mais ça a bien travaillé',
                'next_steps' => 'reprendre la séquence au sol avec musique',
                'dropped_leads' => 'le travail sur chaise, pas adapté à cette salle', 'keep_in_class' => false,
                'additional_notes' => 'il manque des tapis', 'edit_deadline' => '2026-10-01', 'submitted_at' => '2026-09-10 19:05:00',
                'unlocked_by' => 1, 'unlocked_at' => '2026-09-21 09:15:00',
            ],
            [
                'id' => 3, 'scheduled_session_id' => 4, 'author_id' => 3, 'status' => 'submitted',
                'objectives' => 'Découverte de KLAP et des métiers du plateau',
                'adjustment_done' => false, 'adjustment_detail' => null,
                'highlights' => 'ils ont pu voir 10 min de répétition de la compagnie',
                'tools_resources' => 'visite technique avec le régisseur', 'learnings' => 'vocabulaire du plateau',
                'artist_review' => 'les deux groupes très curieux, plein de questions',
                'next_steps' => "atelier du regard sur la pièce qu'ils ont vue", 'dropped_leads' => null, 'keep_in_class' => true,
                'additional_notes' => null, 'edit_deadline' => '2026-09-25', 'submitted_at' => '2026-09-15 17:30:00',
            ],
            [
                'id' => 4, 'scheduled_session_id' => 5, 'author_id' => 3, 'status' => 'draft',
                'objectives' => 'Composition en trio', 'edit_deadline' => '2026-09-27',
            ],
            [
                'id' => 5, 'scheduled_session_id' => 9, 'author_id' => 4, 'status' => 'locked',
                'objectives' => 'Voir un extrait de BRAISES et en parler simplement',
                'adjustment_done' => true, 'adjustment_detail' => 'Le vidéoproj a lâché au milieu, on a fini par une discussion en cercle',
                'highlights' => 'une élève a dit que les danseurs ressemblaient à des braises qui se rallument',
                'tools_resources' => 'vidéoprojecteur, extrait de BRAISES', 'learnings' => 'décrire un mouvement avec ses mots',
                'artist_review' => 'classe vive, tout le monde a parlé',
                'next_steps' => 'passer à la pratique la semaine prochaine', 'dropped_leads' => null, 'keep_in_class' => true,
                'additional_notes' => null, 'edit_deadline' => '2026-09-19', 'submitted_at' => '2026-09-09 17:45:00',
            ],
            [
                'id' => 6, 'scheduled_session_id' => 10, 'author_id' => 4, 'status' => 'submitted',
                'objectives' => 'Reprendre 2 gestes de BRAISES et les transformer',
                'adjustment_done' => false, 'adjustment_detail' => null,
                'highlights' => 'tous les volontaires sont passés devant le groupe',
                'tools_resources' => 'enceinte', 'learnings' => 'transformer un geste, amplitude',
                'artist_review' => 'bonne énergie mais tensions au début entre deux élèves',
                'next_steps' => 'petits groupes mixtes',
                'dropped_leads' => "les consignes trop longues à l'oral, ils décrochent", 'keep_in_class' => true,
                'additional_notes' => 'salle un peu petite pour 23', 'edit_deadline' => '2026-09-26', 'submitted_at' => '2026-09-16 18:10:00',
            ],
            [
                'id' => 7, 'scheduled_session_id' => 12, 'author_id' => 6, 'status' => 'submitted',
                'objectives' => 'Présenter le projet aux familles, noter leurs envies',
                'adjustment_done' => false, 'adjustment_detail' => null,
                'highlights' => 'un grand-père veut nous apprendre une danse de quand il était petit',
                'tools_resources' => null, 'learnings' => null, 'artist_review' => 'très bonne ambiance',
                'next_steps' => 'caler un premier atelier parents / enfants', 'dropped_leads' => null, 'keep_in_class' => false,
                'additional_notes' => null, 'edit_deadline' => '2026-09-21', 'submitted_at' => '2026-09-11 21:00:00',
            ],
            [
                'id' => 8, 'scheduled_session_id' => 13, 'author_id' => 6, 'status' => 'submitted',
                'objectives' => 'Rythme avec le corps et la voix',
                'adjustment_done' => true,
                'adjustment_detail' => "Les enfants étaient plus jeunes que prévu, j'ai simplifié les consignes et raccourci les jeux",
                'highlights' => "l'impro collective à la fin, partie toute seule",
                'tools_resources' => 'petites percus', 'learnings' => 'garder une pulsation ensemble',
                'artist_review' => 'très investis',
                'next_steps' => 'commencer une petite choré commune', 'dropped_leads' => null, 'keep_in_class' => false,
                'additional_notes' => null, 'edit_deadline' => '2026-09-29', 'submitted_at' => '2026-09-19 15:40:00',
            ],
        ];
        foreach ($entries as $entry) {
            $date = $entry['submitted_at'] ?? now();
            DB::table('logbook_entries')->insert($entry + ['created_at' => $date, 'updated_at' => $date]);
        }

        // Partie 4, dans l'ordre : fiche, engagement, expression, autonomie, interaction,
        // sensation, précision, évolution.
        $evaluations = [
            [1, 4, 3, 2, 'écoute, entraide', 'shyness', null, 'first_session'],
            [2, 4, 4, 3, 'écoute', 'buy_in', null, 'improvement'],
            [3, 5, 4, 3, 'curiosité, entraide', 'enthusiasm', null, 'improvement'],
            [4, 3, null, null, null, null, null, null],
            [5, 4, 4, 2, 'écoute', 'enthusiasm', null, 'first_session'],
            [6, 3, 3, 3, 'conflit, puis entraide', 'tension', null, 'improvement'],
            [7, 4, 5, 3, 'partage, écoute', 'other', 'émotion (souvenirs des grands-parents)', 'first_session'],
            [8, 5, 4, 2, 'entraide', 'well_being', null, 'first_session'],
        ];
        foreach ($evaluations as [$entry, $eng, $exp, $auto, $inter, $feeling, $detail, $progress]) {
            DB::table('participant_evaluations')->insert([
                'logbook_entry_id' => $entry, 'engagement' => $eng, 'expression' => $exp, 'autonomy' => $auto,
                'interaction' => $inter, 'feeling' => $feeling, 'feeling_detail' => $detail, 'progress' => $progress,
            ]);
        }

        $exercises = [
            1 => ["marche dans l'espace, stop au signal", 'miroir à deux', 'impro sur 3 verbes (glisser, tomber, repousser)'],
            2 => ['sol : roulades, appuis', 'respiration allongé'],
            3 => ['visite du plateau', 'regarder la répétition'],
            4 => ['échauffement'],
            5 => ['extrait vidéo BRAISES', "cercle : 3 mots pour décrire ce qu'on a vu"],
            6 => ['reprise de 2 gestes de la pièce', 'les transformer (lent / grand / au sol)', 'passage devant les autres'],
            7 => ['présentation dansée', 'on note les envies de chacun'],
            8 => ['pulsation en cercle', 'écho rythmique', 'impro collective'],
        ];
        foreach ($exercises as $entry => $labels) {
            foreach ($labels as $i => $label) {
                DB::table('exercises')->insert(['logbook_entry_id' => $entry, 'position' => $i + 1, 'label' => $label]);
            }
        }

        // Partie 5 : les artistes présents (fiche, compte, rôle).
        $artists = [[1, 3, 'lead'], [2, 3, 'lead'], [3, 3, 'lead'], [3, 5, 'occasional'], [4, 3, 'lead'],
            [5, 4, 'lead'], [6, 4, 'lead'], [6, 5, 'occasional'], [7, 6, 'lead'], [8, 6, 'lead'], [8, 4, 'occasional']];
        foreach ($artists as [$entry, $user, $role]) {
            DB::table('logbook_entry_user')->insert(['logbook_entry_id' => $entry, 'user_id' => $user, 'member_role' => $role]);
        }

        DB::table('custom_field_values')->insert([
            ['logbook_entry_id' => 1, 'custom_field_id' => 1, 'value' => "impro et compo, en lien avec l'épreuve de spé"],
            ['logbook_entry_id' => 1, 'custom_field_id' => 2, 'value' => '1'],
            ['logbook_entry_id' => 3, 'custom_field_id' => 1, 'value' => 'métiers du spectacle vivant'],
            ['logbook_entry_id' => 5, 'custom_field_id' => 3, 'value' => '4'],
            ['logbook_entry_id' => 6, 'custom_field_id' => 3, 'value' => '3'],
        ]);

        $notifications = [
            [1, 'entry_submitted', 3, 'Léa Fournier a rempli la fiche du 15 septembre (Arts Danse).', '2026-09-16 08:30:00', '2026-09-15 17:30:00'],
            [1, 'entry_submitted', 6, 'Hugo Martin a rempli la fiche du 16 septembre (BRAISES).', null, '2026-09-16 18:10:00'],
            [1, 'entry_submitted', 7, 'Samir Benali a rempli la fiche du 11 septembre (AIMER).', '2026-09-12 09:00:00', '2026-09-11 21:00:00'],
            [1, 'entry_submitted', 8, 'Samir Benali a rempli la fiche du 19 septembre (Étincelles).', null, '2026-09-19 15:40:00'],
            [1, 'unlock_request', 2, 'Léa Fournier demande à rouvrir la fiche du 10 septembre.', '2026-09-21 09:14:00', '2026-09-20 22:10:00'],
            [1, 'invitation_accepted', null, 'Samir Benali a activé son compte.', '2026-09-02 12:00:00', '2026-09-02 11:00:00'],
            [3, 'reminder', null, "La fiche de la séance du 18 septembre n'a pas encore été remplie.", null, '2026-09-20 08:00:00'],
        ];
        foreach ($notifications as [$to, $type, $entry, $message, $readAt, $createdAt]) {
            DB::table('klap_notifications')->insert([
                'recipient_id' => $to, 'type' => $type, 'logbook_entry_id' => $entry, 'message' => $message,
                'read_at' => $readAt, 'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]);
        }

        DB::table('report_exports')->insert([
            'project_id' => 1, 'funder_id' => 1, 'generated_by' => 1,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-20',
            'options' => json_encode([
                'sections' => ['generalites', 'objectifs', 'seance', 'participants', 'artistes', 'suite'],
                'indicateurs' => ['engagement', 'expression', 'autonomie', 'frequentation'],
            ]),
            'created_at' => '2026-09-20 16:00:00', 'updated_at' => '2026-09-20 16:00:00',
        ]);
    }
}
