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
