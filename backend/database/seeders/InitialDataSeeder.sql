-- Données de départ pour le dev et les démos
-- A lancer après schema.sql. Le script vide les tables avant, donc on peut le relancer.
-- Mot de passe de tous les comptes : Klap2026!
-- Les intervenants, les mails en @klap.demo et le contenu des fiches sont inventés.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE report_exports;
TRUNCATE TABLE notifications;
TRUNCATE TABLE custom_field_values;
TRUNCATE TABLE custom_fields;
TRUNCATE TABLE logbook_entry_artists;
TRUNCATE TABLE exercises;
TRUNCATE TABLE participant_evaluations;
TRUNCATE TABLE logbook_entries;
TRUNCATE TABLE session_sub_projects;
TRUNCATE TABLE scheduled_sessions;
TRUNCATE TABLE project_members;
TRUNCATE TABLE sub_projects;
TRUNCATE TABLE project_funders;
TRUNCATE TABLE projects;
TRUNCATE TABLE funders;
TRUNCATE TABLE locations;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE invitations;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

SET @pwd = '$2y$10$ySQo0soirW5E0WyOnfYfM.WeEVeA5TLunqklaWuKSIcCGIHre5r7i';

-- users
INSERT INTO users (id, first_name, last_name, email, password_hash, role, phone, is_active, last_login_at, created_at) VALUES
  (1, 'Mariette', 'Travard',  'mariette.travard@klap.demo', @pwd, 'admin',       NULL, 1, '2026-09-21 09:12:41', '2026-08-25 10:03:12'),
  (2, 'Emilie',   'Garetier', 'emilie.garetier@klap.demo',  @pwd, 'super_user',  NULL, 1, '2026-09-19 17:38:02', '2026-08-25 10:06:47'),
  (3, 'Léa',      'Fournier', 'lea.fournier@klap.demo',     @pwd, 'contributor', NULL, 1, '2026-09-18 12:26:15', '2026-08-28 14:11:30'),
  (4, 'Hugo',     'Martin',   'hugo.martin@klap.demo',      @pwd, 'contributor', NULL, 1, NULL, '2026-08-28 14:12:05'),
  (5, 'Nina',     'Roussel',  'nina.roussel@klap.demo',     @pwd, 'contributor', NULL, 1, '2026-09-15 12:51:33', '2026-08-29 09:34:20'),
  (6, 'Samir',    'Benali',   'samir.benali@klap.demo',     @pwd, 'contributor', NULL, 1, '2026-09-19 19:02:58', '2026-09-02 11:00:09'),
  (7, 'Claire',   'Dubois',   'claire.dubois@klap.demo',    @pwd, 'contributor', NULL, 0, '2026-06-30 18:00:00', '2025-09-10 10:00:00');

-- Samir a accepté son invitation, celle d'Inès est encore en attente
INSERT INTO invitations (id, email, full_name, role, token_hash, invited_by, user_id, sent_at, expires_at, accepted_at) VALUES
  (1, 'samir.benali@klap.demo', 'Samir Benali', 'contributor', SHA2('demo-token-samir', 256), 1, 6,    '2026-09-01 15:00:00', '2026-09-08 15:00:00', '2026-09-02 11:00:00'),
  (2, 'ines.morel@klap.demo',   'Inès Morel',   'contributor', SHA2('demo-token-ines', 256),  1, NULL, '2026-09-20 10:30:00', '2026-09-27 10:30:00', NULL);

-- les 3 salles de KLAP + 2 lieux extérieurs
INSERT INTO locations (id, name, address, postal_code, city, is_klap, created_by) VALUES
  (1, 'Petit studio',        '5 avenue Rostand', '13003', 'Marseille', 1, 1),
  (2, 'Grand studio',        '5 avenue Rostand', '13003', 'Marseille', 1, 1),
  (3, 'Salle de création',   '5 avenue Rostand', '13003', 'Marseille', 1, 1),
  (4, 'Lycée Saint-Charles', NULL,               '13003', 'Marseille', 0, 1),
  (5, 'Salle polyvalente',   NULL,               '13400', 'Aubagne',   0, 6);


INSERT INTO funders (id, name, type) VALUES
  (1, 'DRAC Provence-Alpes-Côte d''Azur', 'state'),
  (2, 'Ville de Marseille',               'local_authority'),
  (3, 'Région Sud',                       'local_authority'),
  (4, 'Département des Bouches-du-Rhône', 'local_authority'),
  (5, 'Fondation (démo)',                 'foundation');

-- le dernier projet est archivé, pour tester les filtres
INSERT INTO projects (id, name, description, start_date, end_date, status, default_location_id, created_by, archived_at) VALUES
  (1, 'Arts Danse au Lycée Charles', 'Option et spécialité danse, ateliers + visites + spectacles à KLAP', '2026-09-01', '2027-06-30', 'active', 4, 1, NULL),
  (2, 'Approche de la création chorégraphique : autour de BRAISES', '4 collèges, parcours autour de la pièce BRAISES', '2026-09-01', '2027-05-31', 'active', 3, 1, NULL),
  (3, 'AIMER', NULL, '2026-09-01', '2027-06-30', 'active', 1, 1, NULL),
  (4, 'Classes cousines', '2 classes jumelées sur un même parcours', '2026-10-01', '2027-04-30', 'draft', 2, 1, NULL),
  (5, 'Étincelles', 'création en territoire 2026', '2026-09-01', '2026-12-31', 'active', 5, 1, NULL),
  (6, 'Projet pilote 2025', 'saison dernière, gardé pour l''historique', '2025-01-06', '2025-06-27', 'archived', 2, 1, '2025-07-15 10:00:00');

INSERT INTO project_funders (project_id, funder_id) VALUES
  (1, 1), (1, 3),
  (2, 1), (2, 2),
  (3, 4),
  (4, 2),
  (5, 5), (5, 2),
  (6, 1);

INSERT INTO sub_projects (id, project_id, name, organization, class_group, age_range, city, postal_code, expected_headcount) VALUES
  (1,  1, 'Terminales spécialité danse',       'Lycée Saint-Charles', 'Terminale spé.',  '17-18 ans', 'Marseille', '13003', 20),
  (2,  1, 'Premières spécialité danse',        'Lycée Saint-Charles', 'Première spé.',   '16-17 ans', 'Marseille', '13003', 19),
  (3,  1, 'Secondes option danse',             'Lycée Saint-Charles', 'Seconde option',  '15-16 ans', 'Marseille', '13003', 24),
  (4,  1, 'Premières et Terminales option danse','Lycée Saint-Charles','1re / Tle option','16-18 ans', 'Marseille', '13003', 22),
  (5,  2, 'Collège Aragon',                    'Collège Aragon',      '4e',              '13-14 ans', 'Marseille', NULL,    26),
  (6,  2, 'Collège Brassens',                  'Collège Brassens',    '4e',              '13-14 ans', 'Marseille', NULL,    25),
  (7,  2, 'Collège de L''Estaque',             'Collège de L''Estaque','3e',             '14-15 ans', 'Marseille', NULL,    24),
  (8,  2, 'Collège Izzo',                      'Collège Izzo',        '4e',              '13-14 ans', 'Marseille', NULL,    27),
  (9,  3, 'AIMER',                             NULL,                  'Groupe familles', 'tous âges', 'Marseille', '13003', 15),
  (10, 4, 'Classe cousine A',                  'École (démo)',        'CM2',             '10-11 ans', 'Marseille', '13003', 25),
  (11, 4, 'Classe cousine B',                  'École (démo)',        'CM2',             '10-11 ans', 'Aubagne',   '13400', 23),
  (12, 5, 'Étincelles',                        NULL,                  'Groupe unique',   '8-12 ans',  'Aubagne',   '13400', 18),
  (13, 6, 'Projet pilote 2025',                NULL,                  'Groupe unique',   '15-18 ans', 'Marseille', '13003', 16);

INSERT INTO project_members (project_id, user_id, member_role) VALUES
  (1, 3, 'lead'), (1, 5, 'occasional'),
  (2, 4, 'lead'), (2, 5, 'occasional'),
  (3, 6, 'lead'),
  (4, 3, 'lead'),
  (5, 6, 'lead'), (5, 4, 'occasional'),
  (6, 7, 'lead');

-- planning (on est le 21/09/2026 dans les données)
INSERT INTO scheduled_sessions (id, project_id, session_date, start_time, end_time, location_id, referent_id, session_type, session_type_detail, status, created_by) VALUES
  (1,  1, '2026-09-08', '10:00', '12:00', 4, 3, 'practice_workshop',      NULL, 'done',      1),
  (2,  1, '2026-09-10', '14:00', '16:00', 4, 3, 'practice_workshop',      NULL, 'done',      1),
  (3,  1, '2026-09-12', '10:00', '12:00', 4, 3, 'practice_workshop',      NULL, 'cancelled', 1),
  (4,  1, '2026-09-15', '10:00', '12:00', 2, 3, 'guided_tour',            NULL, 'done',      1),
  (5,  1, '2026-09-17', '14:00', '16:00', 4, 3, 'practice_workshop',      NULL, 'done',      1),
  (6,  1, '2026-09-18', '10:00', '12:00', 4, 3, 'viewing_workshop',       NULL, 'done',      1),
  (7,  1, '2026-09-24', '10:00', '12:00', 4, 3, 'practice_workshop',      NULL, 'planned',   1),
  (8,  1, '2026-10-01', '19:00', '20:30', 3, 3, 'performance',            NULL, 'planned',   1),
  (9,  2, '2026-09-09', '13:30', '15:30', 3, 4, 'viewing_workshop',       NULL, 'done',      1),
  (10, 2, '2026-09-16', '13:30', '15:30', 3, 4, 'practice_workshop',      NULL, 'done',      1),
  (11, 2, '2026-09-29', '13:30', '15:30', 3, 4, 'practice_workshop',      NULL, 'planned',   1),
  (12, 3, '2026-09-11', '18:00', '20:00', 1, 6, 'other', 'Rencontre avec les familles', 'done', 1),
  (13, 5, '2026-09-19', '10:00', '12:00', 5, 6, 'practice_workshop',      NULL, 'done',      1),
  (14, 5, '2026-10-03', '10:00', '12:00', 5, 6, 'complementary_workshop', NULL, 'planned',   1);

-- séance 4 = transversale sur 2 groupes, séance 8 = spectacle pour tout le lycée
INSERT INTO session_sub_projects (session_id, sub_project_id, project_id, attendees) VALUES
  (1,  1, 1, 18),
  (2,  3, 1, 22),
  (3,  4, 1, NULL),
  (4,  1, 1, 17), (4, 2, 1, 19),
  (5,  3, 1, 24),
  (6,  2, 1, NULL),
  (7,  1, 1, NULL),
  (8,  1, 1, NULL), (8, 2, 1, NULL), (8, 3, 1, NULL), (8, 4, 1, NULL),
  (9,  5, 2, 25),
  (10, 6, 2, 23),
  (11, 7, 2, NULL),
  (12, 9, 3, 12),
  (13, 12, 5, 15),
  (14, 12, 5, NULL);

-- fiches : la 2 a été rouverte par Mariette, la 4 est un brouillon.
-- La séance 6 n'a pas de fiche (elle doit sortir dans v_missing_entries)
INSERT INTO logbook_entries (id, session_id, author_id, status, objectives, adjustment_done, adjustment_detail, highlights, tools_resources, learnings, artist_review, next_steps, dropped_leads, keep_in_class, additional_notes, edit_deadline, submitted_at, unlocked_by, unlocked_at) VALUES
  (1, 1, 3, 'locked',
   'Poser un cadre de confiance, premiers pas en impro guidée',
   0, NULL,
   'un élève très discret a proposé la phrase de fin et tout le groupe l''a reprise, beau moment',
   'enceinte + playlist percu', 'écoute, occuper l''espace', 'groupe réceptif, un peu de trac (rentrée)',
   'continuer les duos en miroir', NULL, 1, NULL,
   '2026-09-18', '2026-09-08 18:20:00', NULL, NULL),
  (2, 2, 3, 'submitted',
   'Ancrage au sol, respiration',
   1, 'Salle occupée jusqu''à 14h15, j''ai raccourci l''échauffement et on est passés direct au sol',
   'Rien',
   NULL, 'placement du bassin, relâchement', 'séance dense mais ça a bien travaillé',
   'reprendre la séquence au sol avec musique', 'le travail sur chaise, pas adapté à cette salle', 0, 'il manque des tapis',
   '2026-09-20', '2026-09-10 19:05:00', 1, '2026-09-21 09:15:00'),
  (3, 4, 3, 'submitted',
   'Découverte de KLAP et des métiers du plateau',
   0, NULL,
   'ils ont pu voir 10 min de répétition de la compagnie',
   'visite technique avec le régisseur', 'vocabulaire du plateau', 'les deux groupes très curieux, plein de questions',
   'atelier du regard sur la pièce qu''ils ont vue', NULL, 1, NULL,
   '2026-09-25', '2026-09-15 17:30:00', NULL, NULL),
  (4, 5, 3, 'draft',
   'Composition en trio',
   NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL,
   '2026-09-27', NULL, NULL, NULL),
  (5, 9, 4, 'locked',
   'Voir un extrait de BRAISES et en parler simplement',
   1, 'Le vidéoproj a lâché au milieu, on a fini par une discussion en cercle',
   'une élève a dit que les danseurs ressemblaient à des braises qui se rallument',
   'vidéoprojecteur, extrait de BRAISES', 'décrire un mouvement avec ses mots', 'classe vive, tout le monde a parlé',
   'passer à la pratique la semaine prochaine', NULL, 1, NULL,
   '2026-09-19', '2026-09-09 17:45:00', NULL, NULL),
  (6, 10, 4, 'submitted',
   'Reprendre 2 gestes de BRAISES et les transformer',
   0, NULL,
   'tous les volontaires sont passés devant le groupe',
   'enceinte', 'transformer un geste, amplitude', 'bonne énergie mais tensions au début entre deux élèves',
   'petits groupes mixtes', 'les consignes trop longues à l''oral, ils décrochent', 1, 'salle un peu petite pour 23',
   '2026-09-26', '2026-09-16 18:10:00', NULL, NULL),
  (7, 12, 6, 'submitted',
   'Présenter le projet aux familles, noter leurs envies',
   0, NULL,
   'un grand-père veut nous apprendre une danse de quand il était petit',
   NULL, NULL, 'très bonne ambiance',
   'caler un premier atelier parents / enfants', NULL, 0, NULL,
   '2026-09-21', '2026-09-11 21:00:00', NULL, NULL),
  (8, 13, 6, 'submitted',
   'Rythme avec le corps et la voix',
   1, 'Les enfants étaient plus jeunes que prévu, j''ai simplifié les consignes et raccourci les jeux',
   'l''impro collective à la fin, partie toute seule',
   'petites percus', 'garder une pulsation ensemble', 'très investis',
   'commencer une petite choré commune', NULL, 0, NULL,
   '2026-09-29', '2026-09-19 15:40:00', NULL, NULL);

INSERT INTO participant_evaluations (logbook_entry_id, engagement, expression, autonomy, interaction, feeling, feeling_detail, progress) VALUES
  (1, 4, 3, 2, 'écoute, entraide',       'shyness',    NULL, 'first_session'),
  (2, 4, 4, 3, 'écoute',                 'buy_in',     NULL, 'improvement'),
  (3, 5, 4, 3, 'curiosité, entraide',    'enthusiasm', NULL, 'improvement'),
  (4, 3, NULL, NULL, NULL,               NULL,         NULL, NULL),
  (5, 4, 4, 2, 'écoute','enthusiasm', NULL, 'first_session'),
  (6, 3, 3, 3, 'conflit, puis entraide',  'tension',    NULL, 'improvement'),
  (7, 4, 5, 3, 'partage, écoute',        'other',      'émotion (souvenirs des grands-parents)', 'first_session'),
  (8, 5, 4, 2, 'entraide',               'well_being', NULL, 'first_session');

INSERT INTO exercises (logbook_entry_id, position, label) VALUES
  (1, 1, 'marche dans l''espace, stop au signal'),
  (1, 2, 'miroir à deux'),
  (1, 3, 'impro sur 3 verbes (glisser, tomber, repousser)'),
  (2, 1, 'sol : roulades, appuis'),
  (2, 2, 'respiration allongé'),
  (3, 1, 'visite du plateau'),
  (3, 2, 'regarder la répétition'),
  (4, 1, 'échauffement'),
  (5, 1, 'extrait vidéo BRAISES'),
  (5, 2, 'cercle : 3 mots pour décrire ce qu''on a vu'),
  (6, 1, 'reprise de 2 gestes de la pièce'),
  (6, 2, 'les transformer (lent / grand / au sol)'),
  (6, 3, 'passage devant les autres'),
  (7, 1, 'présentation dansée'),
  (7, 2, 'on note les envies de chacun'),
  (8, 1, 'pulsation en cercle'),
  (8, 2, 'écho rythmique'),
  (8, 3, 'impro collective');

INSERT INTO logbook_entry_artists (logbook_entry_id, user_id, member_role) VALUES
  (1, 3, 'lead'),
  (2, 3, 'lead'),
  (3, 3, 'lead'), (3, 5, 'occasional'),
  (4, 3, 'lead'),
  (5, 4, 'lead'),
  (6, 4, 'lead'), (6, 5, 'occasional'),
  (7, 6, 'lead'),
  (8, 6, 'lead'), (8, 4, 'occasional');

-- champ 2 désactivé le 14/09, ses anciennes valeurs restent
INSERT INTO custom_fields (id, project_id, section, label, field_type, options, is_required, position, is_active, activated_at, deactivated_at) VALUES
  (1, 1, 'session',      'Lien avec le programme de spécialité', 'text',     NULL, 0, 1, 1, '2026-09-01 10:00:00', NULL),
  (2, 1, 'participants', 'Tenue adaptée',                        'checkbox', NULL, 0, 2, 0, '2026-09-01 10:00:00', '2026-09-14 16:00:00'),
  (3, 2, 'participants', 'Niveau d''attention',                  'scale',    JSON_OBJECT('min', 1, 'max', 5), 0, 1, 1, '2026-09-01 10:00:00', NULL);

INSERT INTO custom_field_values (logbook_entry_id, custom_field_id, value) VALUES
  (1, 1, 'impro et compo, en lien avec l''épreuve de spé'),
  (1, 2, '1'),
  (3, 1, 'métiers du spectacle vivant'),
  (5, 3, '4'),
  (6, 3, '3');

INSERT INTO notifications (recipient_id, type, logbook_entry_id, message, read_at, created_at) VALUES
  (1, 'entry_submitted',     3,    'Léa Fournier a rempli la fiche du 15 septembre (Arts Danse).',      '2026-09-16 08:30:00', '2026-09-15 17:30:00'),
  (1, 'entry_submitted',     6,    'Hugo Martin a rempli la fiche du 16 septembre (BRAISES).',          NULL,                  '2026-09-16 18:10:00'),
  (1, 'entry_submitted',     7,    'Samir Benali a rempli la fiche du 11 septembre (AIMER).',           '2026-09-12 09:00:00', '2026-09-11 21:00:00'),
  (1, 'entry_submitted',     8,    'Samir Benali a rempli la fiche du 19 septembre (Étincelles).',      NULL,                  '2026-09-19 15:40:00'),
  (1, 'unlock_request',      2,    'Léa Fournier demande à rouvrir la fiche du 10 septembre.',         '2026-09-21 09:14:00', '2026-09-20 22:10:00'),
  (1, 'invitation_accepted', NULL, 'Samir Benali a activé son compte.',                                 '2026-09-02 12:00:00', '2026-09-02 11:00:00'),
  (3, 'reminder',            NULL, 'La fiche de la séance du 18 septembre n''a pas encore été remplie.', NULL,                  '2026-09-20 08:00:00');

INSERT INTO report_exports (project_id, funder_id, generated_by, period_start, period_end, options, generated_at) VALUES
  (1, 1, 1, '2026-09-01', '2026-09-20',
   JSON_OBJECT('sections', JSON_ARRAY('generalites','objectifs','seance','participants','artistes','suite'),
               'indicateurs', JSON_ARRAY('engagement','expression','autonomie','frequentation')),
   '2026-09-20 16:00:00');
