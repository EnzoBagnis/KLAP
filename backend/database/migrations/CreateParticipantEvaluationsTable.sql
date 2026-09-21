-- P4 - les participants
CREATE TABLE IF NOT EXISTS participant_evaluations (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  engagement        TINYINT UNSIGNED NULL,
  expression        TINYINT UNSIGNED NULL,
  autonomy          TINYINT UNSIGNED NULL,
  interaction       VARCHAR(100) NULL,
  feeling           ENUM('well_being','discomfort','tension','buy_in','enthusiasm','shyness','other') NULL,
  feeling_detail    VARCHAR(100) NULL,
  progress          ENUM('first_session','improvement','stagnation','regression') NULL,
  PRIMARY KEY (logbook_entry_id),
  CONSTRAINT chk_eval_engagement CHECK (engagement BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_expression CHECK (expression BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_autonomy   CHECK (autonomy BETWEEN 1 AND 5),
  CONSTRAINT chk_eval_feeling_detail CHECK (feeling IS NULL OR feeling <> 'other' OR (feeling_detail IS NOT NULL AND CHAR_LENGTH(feeling_detail) > 0)),
  CONSTRAINT fk_eval_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
