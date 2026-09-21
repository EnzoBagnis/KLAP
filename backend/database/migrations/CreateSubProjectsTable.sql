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
