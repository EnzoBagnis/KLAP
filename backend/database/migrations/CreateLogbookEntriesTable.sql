-- fiches du carnet de bord (la partie P4 est dans participant_evaluations).
-- Les checks ne s'appliquent pas aux brouillons
CREATE TABLE IF NOT EXISTS logbook_entries (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id          INT UNSIGNED NOT NULL,
  author_id           INT UNSIGNED NOT NULL,
  status              ENUM('draft','submitted','locked') NOT NULL DEFAULT 'draft',
  -- P2
  objectives          VARCHAR(400) NULL,
  -- P3
  adjustment_done     TINYINT(1)   NULL,   -- fait = 1 / non fait = 0
  adjustment_detail   VARCHAR(500) NULL,
  highlights          VARCHAR(200) NULL,   -- temps forts / anecdotes ("rien" accepté)
  tools_resources     VARCHAR(200) NULL,
  -- P5
  learnings           VARCHAR(200) NULL,   -- les acquis
  artist_review       VARCHAR(200) NULL,
  -- P6
  next_steps          VARCHAR(500) NULL,
  dropped_leads       VARCHAR(500) NULL,   -- pistes à abandonner
  keep_in_class       TINYINT(1)   NOT NULL DEFAULT 0,  -- maintien en classe avec l'équipe enseignante
  additional_notes    VARCHAR(500) NULL,

  edit_deadline       DATE         NULL,   -- date séance + 10j, calculée par l'API
  submitted_at        DATETIME     NULL,
  unlocked_by         INT UNSIGNED NULL,
  unlocked_at         DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entries_session (session_id),  -- 1 fiche max par séance
  KEY idx_entries_author (author_id),
  KEY idx_entries_status (status),
  CONSTRAINT chk_entries_adjustment CHECK (
    status = 'draft'
    OR adjustment_done = 0
    OR (adjustment_done = 1 AND adjustment_detail IS NOT NULL AND CHAR_LENGTH(adjustment_detail) BETWEEN 5 AND 500)
  ),
  CONSTRAINT chk_entries_submitted CHECK (status = 'draft' OR (submitted_at IS NOT NULL AND adjustment_done IS NOT NULL)),
  CONSTRAINT fk_entries_session FOREIGN KEY (session_id) REFERENCES scheduled_sessions (id),
  CONSTRAINT fk_entries_author FOREIGN KEY (author_id) REFERENCES users (id),
  CONSTRAINT fk_entries_unlocked_by FOREIGN KEY (unlocked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
