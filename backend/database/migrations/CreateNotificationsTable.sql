CREATE TABLE IF NOT EXISTS notifications (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_id      INT UNSIGNED NOT NULL,
  type              ENUM('entry_submitted','unlock_request','invitation_accepted','reminder') NOT NULL,
  logbook_entry_id  INT UNSIGNED NULL,
  message           VARCHAR(255) NOT NULL,
  read_at           DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_recipient (recipient_id, read_at),
  CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_entry FOREIGN KEY (logbook_entry_id) REFERENCES logbook_entries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
