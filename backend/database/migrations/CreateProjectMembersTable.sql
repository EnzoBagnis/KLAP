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
