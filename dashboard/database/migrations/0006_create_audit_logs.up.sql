CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NULL,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id VARCHAR(64) NULL,
  ip_address VARCHAR(45) NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME(3) NOT NULL,
  KEY idx_audit_org_time (organization_id, occurred_at),
  KEY idx_audit_actor_time (actor_id, occurred_at),
  CONSTRAINT fk_audit_org FOREIGN KEY (organization_id) REFERENCES organizations (id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
