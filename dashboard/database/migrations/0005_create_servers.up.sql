CREATE TABLE servers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  hostname VARCHAR(253) NOT NULL,
  status ENUM('pending','online','offline') NOT NULL DEFAULT 'pending',
  last_seen_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  UNIQUE KEY uq_servers_org_id (organization_id, id),
  UNIQUE KEY uq_servers_org_hostname (organization_id, hostname),
  KEY idx_servers_org_status (organization_id, status),
  CONSTRAINT fk_servers_org FOREIGN KEY (organization_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE server_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  server_id BIGINT UNSIGNED NOT NULL,
  service_name VARCHAR(190) NOT NULL,
  last_state VARCHAR(32) NOT NULL,
  UNIQUE KEY uq_services_server_name (organization_id, server_id, service_name),
  CONSTRAINT fk_services_server FOREIGN KEY (organization_id, server_id) REFERENCES servers (organization_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
