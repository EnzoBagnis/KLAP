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
