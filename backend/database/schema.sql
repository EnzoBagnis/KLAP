-- KLAP - carnet de bord
-- Création de la base, MySQL 8 (8.0.16 minimum pour les CHECK)
-- Basé sur le MCD : docs/conception/bdd/MCD-carnet-de-bord-KLAP.pdf
--
-- mysql -u root -p klap < backend/database/schema.sql
-- /!\ supprime les tables existantes avant de les recréer

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_missing_entries, v_project_indicators, v_project_participants, v_sub_project_participants;
DROP TABLE IF EXISTS report_exports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS custom_field_values;
DROP TABLE IF EXISTS custom_fields;
DROP TABLE IF EXISTS logbook_entry_artists;
DROP TABLE IF EXISTS exercises;
DROP TABLE IF EXISTS participant_evaluations;
DROP TABLE IF EXISTS logbook_entries;
DROP TABLE IF EXISTS session_sub_projects;
DROP TABLE IF EXISTS scheduled_sessions;
DROP TABLE IF EXISTS project_members;
DROP TABLE IF EXISTS sub_projects;
DROP TABLE IF EXISTS project_funders;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS funders;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS invitations;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- comptes (admin = Mariette, super_user = Emilie, contributor = intervenants)
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,   -- password_hash() côté PHP
  role            ENUM('admin','super_user','contributor') NOT NULL DEFAULT 'contributor',
  phone           VARCHAR(20)  NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at   DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pas d'inscription libre, un compte se crée uniquement via une invitation
CREATE TABLE IF NOT EXISTS invitations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  role            ENUM('admin','super_user','contributor') NOT NULL DEFAULT 'contributor',
  token_hash      CHAR(64)     NOT NULL,   -- sha256 du token envoyé par mail
  invited_by      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,       -- rempli quand le compte est activé
  sent_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME     NOT NULL,
  accepted_at     DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitations_token (token_hash),
  KEY idx_invitations_email (email),
  CONSTRAINT fk_invitations_invited_by FOREIGN KEY (invited_by) REFERENCES users (id),
  CONSTRAINT fk_invitations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mot de passe oublié
CREATE TABLE IF NOT EXISTS password_resets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  token_hash      CHAR(64)     NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME     NOT NULL,
  used_at         DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- lieux, n'importe quel utilisateur peut en ajouter (projets en tournée)
CREATE TABLE IF NOT EXISTS locations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  address         VARCHAR(255) NULL,
  postal_code     CHAR(5)      NOT NULL,
  city            VARCHAR(100) NOT NULL,
  is_klap         TINYINT(1)   NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_locations_name_cp (name, postal_code),
  CONSTRAINT chk_locations_postal_code CHECK (postal_code REGEXP '^[0-9]{5}$'),
  CONSTRAINT fk_locations_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- financeurs
CREATE TABLE IF NOT EXISTS funders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  type            ENUM('state','local_authority','foundation','other') NOT NULL DEFAULT 'other',
  PRIMARY KEY (id),
  UNIQUE KEY uq_funders_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- projets. On ne les supprime pas, on les archive
CREATE TABLE IF NOT EXISTS projects (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                VARCHAR(150) NOT NULL,
  description         TEXT         NULL,
  start_date          DATE         NULL,
  end_date            DATE         NULL,
  status              ENUM('draft','active','finished','archived') NOT NULL DEFAULT 'active',
  default_location_id INT UNSIGNED NOT NULL,
  created_by          INT UNSIGNED NOT NULL,
  archived_at         DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_status (status),
  CONSTRAINT chk_projects_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date),
  CONSTRAINT fk_projects_location FOREIGN KEY (default_location_id) REFERENCES locations (id),
  CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_funders (
  project_id      INT UNSIGNED NOT NULL,
  funder_id       INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, funder_id),
  CONSTRAINT fk_project_funders_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_funders_funder FOREIGN KEY (funder_id) REFERENCES funders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sous-projets (classe, collège...). Un projet a toujours au moins un sous-projet
CREATE TABLE IF NOT EXISTS sub_projects (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id          INT UNSIGNED NOT NULL,
  name                VARCHAR(150) NOT NULL,
  organization        VARCHAR(150) NULL,
  class_group         VARCHAR(100) NULL,
  age_range           VARCHAR(30)  NULL,
  city                VARCHAR(100) NULL,
  postal_code         CHAR(5)      NULL,
  expected_headcount  SMALLINT UNSIGNED NULL,
  is_active           TINYINT(1)   NOT NULL DEFAULT 1,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub_projects_id_project (id, project_id),  -- utilisé par la FK composite de session_sub_projects
  KEY idx_sub_projects_project (project_id),
  CONSTRAINT chk_sub_projects_postal_code CHECK (postal_code IS NULL OR postal_code REGEXP '^[0-9]{5}$'),
  CONSTRAINT fk_sub_projects_project FOREIGN KEY (project_id) REFERENCES projects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- intervenants rattachés à un projet, sert à pré-remplir les artistes de la fiche
CREATE TABLE IF NOT EXISTS project_members (
  project_id      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  member_role     ENUM('lead','occasional') NOT NULL DEFAULT 'lead',
  start_date      DATE NULL,
  end_date        DATE NULL,
  PRIMARY KEY (project_id, user_id),
  KEY idx_project_members_user (user_id),
  CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- planning : la séance existe avant la fiche
CREATE TABLE IF NOT EXISTS scheduled_sessions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id          INT UNSIGNED NOT NULL,
  session_date        DATE         NOT NULL,
  start_time          TIME         NOT NULL,
  end_time            TIME         NOT NULL,
  location_id         INT UNSIGNED NOT NULL,
  referent_id         INT UNSIGNED NOT NULL,  -- l'intervenant qui remplit la fiche
  session_type        ENUM('guided_tour','viewing_workshop','practice_workshop','complementary_workshop','performance','ongoing_project','other') NOT NULL,
  session_type_detail VARCHAR(100) NULL,
  status              ENUM('planned','done','cancelled') NOT NULL DEFAULT 'planned',
  created_by          INT UNSIGNED NOT NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sessions_id_project (id, project_id),
  KEY idx_sessions_date (session_date),
  KEY idx_sessions_referent (referent_id),
  CONSTRAINT chk_sessions_times CHECK (end_time > start_time),
  CONSTRAINT chk_sessions_type_detail CHECK (session_type <> 'other' OR (session_type_detail IS NOT NULL AND CHAR_LENGTH(session_type_detail) > 0)),
  CONSTRAINT fk_sessions_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT fk_sessions_location FOREIGN KEY (location_id) REFERENCES locations (id),
  CONSTRAINT fk_sessions_referent FOREIGN KEY (referent_id) REFERENCES users (id),
  CONSTRAINT fk_sessions_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- groupes concernés par une séance (plusieurs = fiche transversale).
-- project_id sert à vérifier que les groupes sont bien du même projet que la séance
CREATE TABLE IF NOT EXISTS session_sub_projects (
  session_id      INT UNSIGNED NOT NULL,
  sub_project_id  INT UNSIGNED NOT NULL,
  project_id      INT UNSIGNED NOT NULL,
  attendees       SMALLINT UNSIGNED NULL,  -- nb de présents, saisi dans la fiche
  PRIMARY KEY (session_id, sub_project_id),
  KEY idx_ssp_sub_project (sub_project_id, project_id),
  CONSTRAINT fk_ssp_session FOREIGN KEY (session_id, project_id) REFERENCES scheduled_sessions (id, project_id) ON DELETE CASCADE,
  CONSTRAINT fk_ssp_sub_project FOREIGN KEY (sub_project_id, project_id) REFERENCES sub_projects (id, project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fiches du carnet de bord (la partie P4 est dans participant_evaluations).
-- Les checks ne s'appliquent pas aux brouillons
CREATE TABLE IF NOT EXISTS logbook_entries (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id          INT UNSIGNED NOT NULL,
  author_id           INT UNSIGNED NOT NULL,
  status              ENUM('draft','submitted','locked') NOT NULL DEFAULT 'draft',
  -- P2
  objectives          VARCHAR(400) NULL,
  -- P3
  adjustment_done     TINYINT(1)   NULL,   -- fait = 1 / non fait = 0
  adjustment_detail   VARCHAR(500) NULL,
  highlights          VARCHAR(200) NULL,   -- temps forts / anecdotes ("rien" accepté)
  tools_resources     VARCHAR(200) NULL,
  -- P5
  learnings           VARCHAR(200) NULL,   -- les acquis
  artist_review       VARCHAR(200) NULL,
  -- P6
  next_steps          VARCHAR(500) NULL,
  dropped_leads       VARCHAR(500) NULL,   -- pistes à abandonner
  keep_in_class       TINYINT(1)   NOT NULL DEFAULT 0,  -- maintien en classe avec l'équipe enseignante
  additional_notes    VARCHAR(500) NULL,

  edit_deadline       DATE         NULL,   -- date séance + 10j, calculée par l'API
  submitted_at        DATETIME     NULL,
  unlocked_by         INT UNSIGNED NULL,
  unlocked_at         DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entries_session (session_id),  -- 1 fiche max par séance
  KEY idx_entries_author (author_id),
  KEY idx_entries_status (status),
  CONSTRAINT chk_entries_adjustment CHECK (
    status = 'draft'
    OR adjustment_done = 0
    OR (adjustment_done = 1 AND adjustment_detail IS NOT NULL AND CHAR_LENGTH(adjustment_detail) BETWEEN 5 AND 500)
  ),
  CONSTRAINT chk_entries_submitted CHECK (status = 'draft' OR (submitted_at IS NOT NULL AND adjustment_done IS NOT NULL)),
  CONSTRAINT fk_entries_session FOREIGN KEY (session_id) REFERENCES scheduled_sessions (id),
  CONSTRAINT fk_entries_author FOREIGN KEY (author_id) REFERENCES users (id),
  CONSTRAINT fk_entries_unlocked_by FOREIGN KEY (unlocked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- P4 - les participants
CREATE TABLE IF NOT EXISTS participant_evaluations (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  engagement        TINYINT UNSIGNED NULL,
  expression        TINYINT UNSIGNED NULL,
  autonomy          TINYINT UNSIGNED NULL,
  interaction       VARCHAR(100) NULL,
  feeling           ENUM('well_being','discomfort','tension','buy_in','enthusiasm','shyness','other') NULL,
  feeling_detail    VARCHAR(100) NULL,
  progress          ENUM('first_session','improvement','stagnation','regression') NULL,
  PRIMARY KEY (logbook_entry_id),
  CONSTRAINT chk_eval_engagement CHECK (engagement BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_expression CHECK (expression BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_autonomy   CHECK (autonomy BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_feeling_detail CHECK (feeling IS NULL OR feeling <> 'other' OR (feeling_detail IS NOT NULL AND CHAR_LENGTH(feeling_detail) > 0)),
  CONSTRAINT fk_eval_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exercices de la séance, 10 max (le min de 2 est vérifié dans l'API)
CREATE TABLE IF NOT EXISTS exercises (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  logbook_entry_id  INT UNSIGNED NOT NULL,
  position          TINYINT UNSIGNED NOT NULL,
  label             VARCHAR(200) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exercises_position (logbook_entry_id, position),
  CONSTRAINT chk_exercises_position CHECK (position BETWEEN 1 AND 10),
  CONSTRAINT fk_exercises_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- artistes présents à la séance
CREATE TABLE IF NOT EXISTS logbook_entry_artists (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  member_role       ENUM('lead','occasional') NOT NULL DEFAULT 'lead',
  PRIMARY KEY (logbook_entry_id, user_id),
  CONSTRAINT fk_entry_artists_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_artists_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- champs en plus propres à un projet. On les désactive, on ne les supprime jamais
CREATE TABLE IF NOT EXISTS custom_fields (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id      INT UNSIGNED NOT NULL,
  section         ENUM('general','objectives','session','participants','artists','next_steps') NOT NULL,
  label           VARCHAR(150) NOT NULL,
  field_type      ENUM('text','number','select','checkbox','scale') NOT NULL,
  options         JSON         NULL,  -- choix possibles (select) ou bornes (scale)
  is_required     TINYINT(1)   NOT NULL DEFAULT 0,
  position        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  activated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deactivated_at  DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_custom_fields_project (project_id, section, position),
  CONSTRAINT chk_custom_fields_active CHECK (is_active = 1 OR deactivated_at IS NOT NULL),
  CONSTRAINT fk_custom_fields_project FOREIGN KEY (project_id) REFERENCES projects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_field_values (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  custom_field_id   INT UNSIGNED NOT NULL,
  value             TEXT NULL,
  PRIMARY KEY (logbook_entry_id, custom_field_id),
  CONSTRAINT fk_cfv_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE,
  CONSTRAINT fk_cfv_field FOREIGN KEY (custom_field_id) REFERENCES custom_fields (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_id      INT UNSIGNED NOT NULL,
  type              ENUM('entry_submitted','unlock_request','invitation_accepted','reminder') NOT NULL,
  logbook_entry_id  INT UNSIGNED NULL,
  message           VARCHAR(255) NOT NULL,
  read_at           DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_recipient (recipient_id, read_at),
  CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- historique des exports du pré-bilan (le .docx est généré à la volée)
CREATE TABLE IF NOT EXISTS report_exports (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id      INT UNSIGNED NOT NULL,
  funder_id       INT UNSIGNED NULL,
  generated_by    INT UNSIGNED NOT NULL,
  period_start    DATE NULL,
  period_end      DATE NULL,
  options         JSON NULL,  -- sections / indicateurs cochés
  generated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_report_exports_project (project_id),
  CONSTRAINT chk_report_period CHECK (period_end IS NULL OR period_start IS NULL OR period_end >= period_start),
  CONSTRAINT fk_report_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT fk_report_funder FOREIGN KEY (funder_id) REFERENCES funders (id) ON DELETE SET NULL,
  CONSTRAINT fk_report_generated_by FOREIGN KEY (generated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- vues pour les stats, calculées à partir des fiches soumises (rien n'est stocké)

-- participants d'un sous-projet = max des présents sur ses séances (pas la somme, cf. retour de Mariette)
CREATE OR REPLACE VIEW v_sub_project_participants AS
SELECT ssp.sub_project_id,
       ssp.project_id,
       MAX(ssp.attendees) AS participants,
       COUNT(DISTINCT ssp.session_id) AS sessions_documented
FROM session_sub_projects ssp
JOIN logbook_entries le ON le.session_id = ssp.session_id
WHERE le.status IN ('submitted','locked')
GROUP BY ssp.sub_project_id, ssp.project_id;

-- participants d'un projet = somme des max de chaque sous-projet
CREATE OR REPLACE VIEW v_project_participants AS
SELECT p.id AS project_id,
       p.name,
       COALESCE(SUM(v.participants), 0) AS participants
FROM projects p
LEFT JOIN v_sub_project_participants v ON v.project_id = p.id
GROUP BY p.id, p.name;

-- moyennes engagement / expression / autonomie par projet
CREATE OR REPLACE VIEW v_project_indicators AS
SELECT s.project_id,
       COUNT(*)                     AS entries,
       ROUND(AVG(pe.engagement), 2) AS avg_engagement,
       ROUND(AVG(pe.expression), 2) AS avg_expression,
       ROUND(AVG(pe.autonomy), 2)   AS avg_autonomy
FROM logbook_entries le
JOIN scheduled_sessions s ON s.id = le.session_id
JOIN participant_evaluations pe ON pe.logbook_entry_id = le.id
WHERE le.status IN ('submitted','locked')
GROUP BY s.project_id;

-- séances passées dont la fiche n'a pas été rendue
CREATE OR REPLACE VIEW v_missing_entries AS
SELECT s.id AS session_id, s.project_id, s.session_date, s.referent_id
FROM scheduled_sessions s
LEFT JOIN logbook_entries le ON le.session_id = s.id AND le.status IN ('submitted','locked')
WHERE s.status <> 'cancelled'
  AND s.session_date < CURRENT_DATE
  AND le.id IS NULL;
