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
