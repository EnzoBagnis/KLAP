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
