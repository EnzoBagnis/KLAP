CREATE TABLE IF NOT EXISTS custom_field_values (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  custom_field_id   INT UNSIGNED NOT NULL,
  value             TEXT NULL,
  PRIMARY KEY (logbook_entry_id, custom_field_id),
  CONSTRAINT fk_cfv_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE,
  CONSTRAINT fk_cfv_field FOREIGN KEY (custom_field_id) REFERENCES custom_fields (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
