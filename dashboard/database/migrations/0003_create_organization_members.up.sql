CREATE TABLE organization_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('client_admin','operator','viewer') NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME(3) NOT NULL,
  UNIQUE KEY uq_members_org_user (organization_id, user_id),
  KEY idx_members_user (user_id),
  KEY idx_members_org_status (organization_id, status),
  CONSTRAINT fk_members_org FOREIGN KEY (organization_id) REFERENCES organizations (id),
  CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
