CREATE TABLE organizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(63) NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME(3) NOT NULL,
  UNIQUE KEY uq_organizations_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
