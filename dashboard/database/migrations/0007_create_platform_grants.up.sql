CREATE TABLE platform_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL COMMENT 'must be a platform admin',
  organization_id BIGINT UNSIGNED NOT NULL,
  access_level ENUM('read','manage') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  granted_by BIGINT UNSIGNED NULL,
  created_at DATETIME(3) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  revoked_at DATETIME(3) NULL,
  KEY idx_grants_user_org (user_id, organization_id),
  CONSTRAINT fk_grants_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_grants_org FOREIGN KEY (organization_id) REFERENCES organizations (id),
  CONSTRAINT fk_grants_by FOREIGN KEY (granted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
