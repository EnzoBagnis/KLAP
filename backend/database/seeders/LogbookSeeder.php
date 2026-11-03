<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder des fiches de carnet de bord.
 * Reproduit les fiches de démonstration de la maquette Figma :
 * - Fiche 1 : Verrouillée (délai de 10 jours dépassé)
 * - Fiche 2 : Rouverte par l'administratrice Mariette suite à une demande
 * - Fiche 3 : Remplie normalement (séance transversale)
 * - Fiche 4 : Enregistrée en brouillon (incomplète)
 * - La séance 6 n'a pas de fiche : elle remonte dans les fiches en retard (v_missing_entries)
 */
class LogbookSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'id' => 1,
                'scheduled_session_id' => 1,
                'author_id' => 3, // Léa Fournier
                'status' => 'locked',

                // P1 - Généralités
                'attendees_count' => 18,
                'attendees_male' => 7,
                'attendees_female' => 11,
                'class_or_age' => 'Seconde 3 — 15-16 ans',

                // P2 - Objectifs
                'objectives' => 'Poser un cadre de confiance, premiers pas en improvisation guidée',
                'competencies' => "Écoute mutuelle, occupation consciente de l'espace",
                'progression' => 'Séance 1 : prise de contact et repères corporels',

                // P3 - La séance
                'highlights' => "Un élève très discret a proposé la phrase de fin et tout le groupe l'a reprise, beau moment",
                'closing' => 'Cercle de parole et étirements doux',
                'feedback' => 'Élèves motivés mais impressionnés au départ',
                'tools_resources' => 'Enceinte Bluetooth + playlist percussions',
                'adjustment_done' => false,
                'adjustment_detail' => null,

                // P5 - Les artistes
                'what_works' => 'Groupe réceptif, mise en confiance réussie rapidement',
                'learnings' => "Capacité d'écoute collective et engagement physique",
                'to_watch' => 'Un peu de trac et de retenue chez 2 ou 3 garçons',
                'to_improve' => 'Laisser plus de temps sur la phase de relaxation finale',
                'additional_notes' => 'Très bon groupe pour commencer ce projet Lycée Charles.',

                // P6 - La suite
                'next_steps' => 'Continuer les duos en miroir et introduire le travail au sol',
                'keep_in_class' => true,
                'dropped_leads' => null,
                'participant_wishes' => 'Les élèves ont demandé à réutiliser de la musique avec du rythme',

                // Verrouillage
                'edit_deadline' => '2026-09-18',
                'submitted_at' => '2026-09-08 18:20:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 2,
                'scheduled_session_id' => 2,
                'author_id' => 3,
                'status' => 'submitted',

                // P1
                'attendees_count' => 22,
                'attendees_male' => 9,
                'attendees_female' => 13,
                'class_or_age' => 'Seconde option — 15-16 ans',

                // P2
                'objectives' => 'Ancrage au sol, gestion de la respiration et du poids du corps',
                'competencies' => 'Placement du bassin, relâchement des tensions',
                'progression' => 'Approfondissement des fondamentaux techniques',

                // P3
                'highlights' => 'Bonne concentration générale malgré le changement de salle',
                'closing' => 'Retour au calme allongé',
                'feedback' => 'Ont apprécié le travail au sol, moins intimidant',
                'tools_resources' => 'Tapis de danse',
                'adjustment_done' => true,
                'adjustment_detail' => "Salle occupée jusqu'à 14h15, j'ai raccourci l'échauffement et on est passés direct au sol",

                // P5
                'what_works' => 'Le travail au sol a permis de détendre les plus timides',
                'learnings' => 'Placement du bassin, dissociation haut/bas du corps',
                'to_watch' => 'Fatigue musculaire rapide',
                'to_improve' => 'Prévoir des tapis supplémentaires pour tout le monde',
                'additional_notes' => 'Prévoir des tapis supplémentaires avec le régisseur de KLAP.',

                // P6
                'next_steps' => 'Reprendre la séquence au sol avec musique rythmée',
                'keep_in_class' => false,
                'dropped_leads' => 'Le travail sur chaise, pas adapté à cette salle trop étroite',
                'participant_wishes' => 'Demande de travailler davantage en petits groupes de 3',

                // Déverrouillage par l'admin
                'edit_deadline' => '2026-09-20',
                'submitted_at' => '2026-09-10 19:05:00',
                'unlock_reason' => "Erreur sur le décompte des présences garçons/filles, besoin de corriger l'effectif exact.",
                'unlock_requested_at' => '2026-09-20 22:10:00',
                'unlocked_by' => 1, // Déverrouillé par Mariette Travard
                'unlocked_at' => '2026-09-21 09:15:00',
            ],
            [
                'id' => 3,
                'scheduled_session_id' => 4,
                'author_id' => 3,
                'status' => 'submitted',

                // P1
                'attendees_count' => 36,
                'attendees_male' => 14,
                'attendees_female' => 22,
                'class_or_age' => 'Secondes 3 et 7 — 15-16 ans',

                // P2
                'objectives' => 'Découverte de KLAP Maison pour la danse et des métiers du plateau',
                'competencies' => 'Vocabulaire scénique, regard critique de spectateur',
                'progression' => 'Séance transversale commune aux deux classes',

                // P3
                'highlights' => 'Les élèves ont pu assister à 10 minutes de répétition de la compagnie Kelemenis',
                'closing' => 'Échange informel avec les danseurs de la compagnie',
                'feedback' => 'Très impressionnés par les coulisses et les passerelles techniques',
                'tools_resources' => 'Visite technique commentée avec le régisseur général',
                'adjustment_done' => false,
                'adjustment_detail' => null,

                // P5
                'what_works' => 'L’immersion sur le grand plateau a captivé les élèves',
                'learnings' => 'Repères spatiaux d’un théâtre, vocabulaire (côté cour/jardin)',
                'to_watch' => 'Effectif lourd (36 élèves) : scinder en deux pour la prochaine visite',
                'to_improve' => 'Rythmer davantage les temps de parole assis',
                'additional_notes' => 'Mariette a pu passer saluer les enseignants en début de visite.',

                // P6
                'next_steps' => 'Atelier du regard sur la pièce vue en répétition',
                'keep_in_class' => true,
                'dropped_leads' => null,
                'participant_wishes' => 'Certains veulent savoir comment on devient danseur professionnel',

                'edit_deadline' => '2026-09-25',
                'submitted_at' => '2026-09-15 17:30:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 4,
                'scheduled_session_id' => 5,
                'author_id' => 3,
                'status' => 'draft',

                // P1
                'attendees_count' => null,
                'attendees_male' => null,
                'attendees_female' => null,
                'class_or_age' => 'Seconde 3',

                // P2
                'objectives' => 'Composition chorégraphique en trio',
                'competencies' => null,
                'progression' => null,

                // P3
                'highlights' => null,
                'closing' => null,
                'feedback' => null,
                'tools_resources' => null,
                'adjustment_done' => null,
                'adjustment_detail' => null,

                // P5
                'what_works' => null,
                'learnings' => null,
                'to_watch' => null,
                'to_improve' => null,
                'additional_notes' => null,

                // P6
                'next_steps' => null,
                'keep_in_class' => false,
                'dropped_leads' => null,
                'participant_wishes' => null,

                'edit_deadline' => '2026-09-27',
                'submitted_at' => null,
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 5,
                'scheduled_session_id' => 9,
                'author_id' => 4, // Hugo Martin
                'status' => 'locked',

                'attendees_count' => 25,
                'attendees_male' => 12,
                'attendees_female' => 13,
                'class_or_age' => 'Collège Aragon — 4ème',

                'objectives' => 'Voir un extrait de BRAISES et en parler simplement',
                'competencies' => "Décrire un mouvement avec ses propres mots, écoute de l'autre",
                'progression' => 'Phase 1 : Découverte de l’œuvre de Kelemenis',

                'highlights' => 'Une élève a dit que les danseurs ressemblaient à des braises qui se rallument',
                'closing' => 'Cercle de parole',
                'feedback' => 'Très bonne participation orale',
                'tools_resources' => 'Vidéoprojecteur, extrait vidéo de BRAISES',
                'adjustment_done' => true,
                'adjustment_detail' => 'Le vidéoprojecteur a lâché au milieu, on a fini par une discussion en cercle',

                'what_works' => 'La métaphore des braises a immédiatement parlé aux collégiens',
                'learnings' => 'Exprimer un ressenti sans jugement de valeur',
                'to_watch' => 'Agitation en début de séance',
                'to_improve' => 'Tester le matériel vidéo 15 minutes avant',
                'additional_notes' => null,

                'next_steps' => 'Passer à la pratique corporelle la semaine prochaine',
                'keep_in_class' => true,
                'dropped_leads' => null,
                'participant_wishes' => 'Envie de reproduire le solo vu dans la vidéo',

                'edit_deadline' => '2026-09-19',
                'submitted_at' => '2026-09-09 17:45:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 6,
                'scheduled_session_id' => 10,
                'author_id' => 4,
                'status' => 'submitted',

                'attendees_count' => 23,
                'attendees_male' => 11,
                'attendees_female' => 12,
                'class_or_age' => 'Collège Brassens — 4ème',

                'objectives' => 'Reprendre 2 gestes clés de BRAISES et les transformer',
                'competencies' => 'Variation d’amplitude, gestion des niveaux d’espace (sol/debout)',
                'progression' => 'Atelier de pratique n°2',

                'highlights' => 'Tous les élèves volontaires sont passés devant le groupe',
                'closing' => 'Applaudissements mutuels et débrief',
                'feedback' => 'Fierté d’avoir montré leur courte chorégraphie',
                'tools_resources' => 'Enceinte sono',
                'adjustment_done' => false,
                'adjustment_detail' => null,

                'what_works' => 'Émulation positive lors des passages devant les camarades',
                'learnings' => 'Transformer un geste : variation de vitesse et d’énergie',
                'to_watch' => 'Légères tensions verbales en tout début de cours',
                'to_improve' => 'Constitution des groupes par tirage pour éviter les clans',
                'additional_notes' => 'Salle du collège un peu exigüe pour 23 élèves.',

                'next_steps' => 'Travail en petits groupes mixtes',
                'keep_in_class' => true,
                'dropped_leads' => "Les consignes trop longues à l'oral (les élèves décrochent vite)",
                'participant_wishes' => 'Ajouter des mouvements au sol',

                'edit_deadline' => '2026-09-26',
                'submitted_at' => '2026-09-16 18:10:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 7,
                'scheduled_session_id' => 12,
                'author_id' => 6, // Samir Benali
                'status' => 'submitted',

                'attendees_count' => 12,
                'attendees_male' => 5,
                'attendees_female' => 7,
                'class_or_age' => 'Familles — tous âges',

                'objectives' => 'Présenter le projet AIMER aux familles, recueillir leurs envies',
                'competencies' => 'Transmission intergénérationnelle, convivialité',
                'progression' => 'Séance d’ouverture du projet',

                'highlights' => 'Un grand-père a proposé de nous montrer un pas de danse traditionnelle de son enfance',
                'closing' => 'Goûter partagé',
                'feedback' => 'Parents rassurés et très demandeurs',
                'tools_resources' => 'Musique acoustique',
                'adjustment_done' => false,
                'adjustment_detail' => null,

                'what_works' => 'Ambiance chaleureuse, présence de plusieurs générations',
                'learnings' => 'Dédramatiser la danse contemporaine',
                'to_watch' => 'Attention aux exercices au sol pour les personnes âgées',
                'to_improve' => 'Prévoir des chaises confortables',
                'additional_notes' => 'Très belle amorce pour le projet AIMER.',

                'next_steps' => 'Caler le premier atelier pratique parents/enfants',
                'keep_in_class' => false,
                'dropped_leads' => null,
                'participant_wishes' => 'Danser avec leurs enfants',

                'edit_deadline' => '2026-09-21',
                'submitted_at' => '2026-09-11 21:00:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
            [
                'id' => 8,
                'scheduled_session_id' => 13,
                'author_id' => 6,
                'status' => 'submitted',

                'attendees_count' => 15,
                'attendees_male' => 8,
                'attendees_female' => 7,
                'class_or_age' => 'Enfants 8-12 ans',

                'objectives' => 'Rythme avec le corps et la voix',
                'competencies' => 'Coordination motrice et synchronisation de groupe',
                'progression' => 'Cycle Étincelles 2026',

                'highlights' => 'L’improvisation collective finale a démarré spontanément',
                'closing' => 'Ronde finale',
                'feedback' => 'Beaucoup d’énergie et de rires',
                'tools_resources' => 'Petites percussions en bois',
                'adjustment_done' => true,
                'adjustment_detail' => "Les enfants étaient plus jeunes que prévu, j'ai simplifié les consignes et raccourci les jeux",

                'what_works' => 'Les percussions corporelles fonctionnent à tous les coups',
                'learnings' => 'Garder une pulsation commune',
                'to_watch' => 'Dissipation rapide si la consigne dépasse 1 minute',
                'to_improve' => 'Alterner temps très calmes et explosions d’énergie',
                'additional_notes' => null,

                'next_steps' => 'Créer une courte phrase rythmique commune',
                'keep_in_class' => false,
                'dropped_leads' => null,
                'participant_wishes' => 'Utiliser d’autres instruments de musique',

                'edit_deadline' => '2026-09-29',
                'submitted_at' => '2026-09-19 15:40:00',
                'unlock_reason' => null,
                'unlock_requested_at' => null,
                'unlocked_by' => null,
                'unlocked_at' => null,
            ],
        ];

        foreach ($entries as $entry) {
            $date = $entry['submitted_at'] ?? now();
            DB::table('logbook_entries')->insert($entry + ['created_at' => $date, 'updated_at' => $date]);
        }

        // P4 : Évaluations des participants
        // logbook_entry_id, engagement (1-5), expression (1-5), autonomie (1-5), interaction, feeling, feeling_detail, progress
        $evaluations = [
            [1, 4, 3, 2, 'écoute, entraide', 'shyness', null, 'first_session'],
            [2, 4, 4, 3, 'écoute, concentration', 'buy_in', null, 'improvement'],
            [3, 5, 4, 3, 'curiosité, entraide', 'enthusiasm', null, 'improvement'],
            [4, 3, null, null, null, null, null, null],
            [5, 4, 4, 2, 'écoute attentive', 'enthusiasm', null, 'first_session'],
            [6, 3, 3, 3, 'conflit au début, puis entraide', 'tension', null, 'improvement'],
            [7, 4, 5, 3, 'partage, écoute intergénérationnelle', 'other', 'émotion (souvenirs des aînés)', 'first_session'],
            [8, 5, 4, 2, 'entraide et rires', 'well_being', null, 'first_session'],
        ];

        foreach ($evaluations as [$entry, $eng, $exp, $auto, $inter, $feeling, $detail, $progress]) {
            DB::table('participant_evaluations')->insert([
                'logbook_entry_id' => $entry,
                'engagement' => $eng,
                'expression' => $exp,
                'autonomy' => $auto,
                'interaction' => $inter,
                'feeling' => $feeling,
                'feeling_detail' => $detail,
                'progress' => $progress,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // P3 : Exercices de chaque séance (1 à 10 max)
        $exercises = [
            1 => ["Marche dans l'espace, stop au signal musical", 'Miroir à deux (guidage lent)', 'Improvisation sur 3 verbes : glisser, tomber, repousser'],
            2 => ['Au sol : roulades et appuis', 'Respiration allongé et détente du bassin'],
            3 => ['Visite technique du grand plateau', 'Observation de la répétition des danseurs'],
            4 => ['Échauffement articulaire en cercle'],
            5 => ['Visionnage de l’extrait vidéo de BRAISES', "Cercle de parole : 3 mots pour décrire ce qu'on a vu"],
            6 => ['Reprise de 2 gestes de la pièce BRAISES', 'Les transformer (lent / grand / au sol)', 'Passage chorégraphique devant les autres'],
            7 => ['Présentation dansée informelle', 'Tour de table des envies de chaque famille'],
            8 => ['Pulsation en cercle', 'Écho rythmique avec les mains', 'Improvisation collective en déplacement'],
        ];

        foreach ($exercises as $entry => $labels) {
            foreach ($labels as $i => $label) {
                DB::table('exercises')->insert([
                    'logbook_entry_id' => $entry,
                    'position' => $i + 1,
                    'label' => $label,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // P5 : Artistes présents à chaque séance
        $artists = [
            [1, 3, 'lead'], [2, 3, 'lead'], [3, 3, 'lead'], [3, 5, 'occasional'], [4, 3, 'lead'],
            [5, 4, 'lead'], [6, 4, 'lead'], [6, 5, 'occasional'], [7, 6, 'lead'], [8, 6, 'lead'], [8, 4, 'occasional'],
        ];

        foreach ($artists as [$entry, $user, $role]) {
            DB::table('logbook_entry_user')->insert([
                'logbook_entry_id' => $entry,
                'user_id' => $user,
                'member_role' => $role,
            ]);
        }

        // Réponses aux champs personnalisés
        DB::table('custom_field_values')->insert([
            ['logbook_entry_id' => 1, 'custom_field_id' => 1, 'value' => "Improvisation et composition, en lien avec l'épreuve de spécialité danse", 'created_at' => now(), 'updated_at' => now()],
            ['logbook_entry_id' => 1, 'custom_field_id' => 2, 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['logbook_entry_id' => 3, 'custom_field_id' => 1, 'value' => 'Métiers du spectacle vivant et régie plateau', 'created_at' => now(), 'updated_at' => now()],
            ['logbook_entry_id' => 5, 'custom_field_id' => 3, 'value' => '4', 'created_at' => now(), 'updated_at' => now()],
            ['logbook_entry_id' => 6, 'custom_field_id' => 3, 'value' => '3', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Notifications de démonstration
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
                'recipient_id' => $to,
                'type' => $type,
                'logbook_entry_id' => $entry,
                'message' => $message,
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // Historique de bilans déjà exportés
        DB::table('report_exports')->insert([
            'project_id' => 1,
            'funder_id' => 1,
            'generated_by' => 1,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-20',
            'options' => json_encode([
                'sections' => ['generalites', 'objectifs', 'seance', 'participants', 'artistes', 'suite'],
                'indicateurs' => ['engagement', 'expression', 'autonomie', 'frequentation'],
            ]),
            'created_at' => '2026-09-20 16:00:00',
            'updated_at' => '2026-09-20 16:00:00',
        ]);
    }
}
