-- financeurs
CREATE TABLE IF NOT EXISTS funders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  type            ENUM('state','local_authority','foundation','other') NOT NULL DEFAULT 'other',
  PRIMARY KEY (id),
  UNIQUE KEY uq_funders_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
