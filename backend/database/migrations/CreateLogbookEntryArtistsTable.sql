-- artistes présents à la séance
CREATE TABLE IF NOT EXISTS logbook_entry_artists (
  logbook_entry_id  INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  member_role       ENUM('lead','occasional') NOT NULL DEFAULT 'lead',
  PRIMARY KEY (logbook_entry_id, user_id),
  CONSTRAINT fk_entry_artists_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_artists_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
