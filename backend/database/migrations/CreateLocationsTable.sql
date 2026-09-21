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
