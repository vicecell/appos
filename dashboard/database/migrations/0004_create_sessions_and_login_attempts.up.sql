CREATE TABLE sessions (
  id CHAR(64) NOT NULL PRIMARY KEY COMMENT 'sha256 of the session token; the token itself is never stored',
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(3) NOT NULL,
  last_seen_at DATETIME(3) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  identifier_hash CHAR(64) NOT NULL COMMENT 'sha256 of normalized email',
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME(3) NOT NULL,
  KEY idx_attempts_identifier (identifier_hash, attempted_at),
  KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
