CREATE TABLE IF NOT EXISTS project_funders (
  project_id      INT UNSIGNED NOT NULL,
  funder_id       INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, funder_id),
  CONSTRAINT fk_project_funders_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_funders_funder FOREIGN KEY (funder_id) REFERENCES funders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
