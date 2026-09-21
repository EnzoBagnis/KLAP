-- pas d'inscription libre, un compte se crée uniquement via une invitation
CREATE TABLE IF NOT EXISTS invitations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  role            ENUM('admin','super_user','contributor') NOT NULL DEFAULT 'contributor',
  token_hash      CHAR(64)     NOT NULL,   -- sha256 du token envoyé par mail
  invited_by      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,       -- rempli quand le compte est activé
  sent_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME     NOT NULL,
  accepted_at     DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitations_token (token_hash),
  KEY idx_invitations_email (email),
  CONSTRAINT fk_invitations_invited_by FOREIGN KEY (invited_by) REFERENCES users (id),
  CONSTRAINT fk_invitations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
