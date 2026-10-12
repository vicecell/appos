# Data model

Target schema: `bahan/ServerWatch_Technical_Specification.md` section 4.

Rules: BIGINT UNSIGNED keys, UTC `DATETIME(3)`, InnoDB, utf8mb4, `organization_id` on every tenant-scoped table with
composite indexes, no raw metric samples in MySQL.

Migrations live in `dashboard/database/migrations/` as `NNNN_name.up.sql` + `NNNN_name.down.sql`. Applied versions
are tracked in `schema_migrations`. Run with `php dashboard/bin/migrate.php up|down|status` (`down` reverts one step).

SW-001: no domain tables yet; they start in SW-002.
