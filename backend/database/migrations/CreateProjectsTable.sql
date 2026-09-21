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
