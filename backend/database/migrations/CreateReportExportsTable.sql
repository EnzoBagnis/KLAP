-- historique des exports du pré-bilan (le .docx est généré à la volée)
CREATE TABLE IF NOT EXISTS report_exports (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id      INT UNSIGNED NOT NULL,
  funder_id       INT UNSIGNED NULL,
  generated_by    INT UNSIGNED NOT NULL,
  period_start    DATE NULL,
  period_end      DATE NULL,
  options         JSON NULL,  -- sections / indicateurs cochés
  generated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_report_exports_project (project_id),
  CONSTRAINT chk_report_period CHECK (period_end IS NULL OR period_start IS NULL OR period_end >= period_start),
  CONSTRAINT fk_report_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT fk_report_funder FOREIGN KEY (funder_id) REFERENCES funders (id) ON DELETE SET NULL,
  CONSTRAINT fk_report_generated_by FOREIGN KEY (generated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
