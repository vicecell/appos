# Data model

Target schema: `bahan/ServerWatch_Technical_Specification.md` sections 4 and 15.

Rules: BIGINT UNSIGNED keys, UTC `DATETIME(3)`, InnoDB, utf8mb4, `organization_id` on every tenant-scoped table with
a leading composite index, composite foreign keys for tenant-owned references, no raw metric samples in MySQL.

Migrations live in `dashboard/database/migrations/` as `NNNN_name.up.sql` + `NNNN_name.down.sql` (reversibility is
enforced by `MigrationCatalog`). Applied versions are tracked in `schema_migrations`.
`php dashboard/bin/migrate.php up|down|status` (`down` reverts one step).

## Implemented (SW-002)

| Migration | Tables | Notes |
|---|---|---|
| 0001 | `organizations` | UNIQUE slug, status active/suspended |
| 0002 | `users` | UNIQUE email, Argon2id hash, `mfa_secret_enc` (secretbox), `mfa_last_step`, `is_platform_admin` |
| 0003 | `organization_members` | UNIQUE (organization_id, user_id); roles client_admin/operator/viewer |
| 0004 | `sessions`, `login_attempts` | session id = SHA-256 of token; attempts keyed by hashed e-mail and IP |
| 0005 | `servers`, `server_services` | `servers` UNIQUE (organization_id, id) so `server_services` can use a composite FK that makes cross-tenant references impossible |
| 0006 | `audit_logs` | append-only by convention; `organization_id` nullable |
| 0007 | `platform_grants` | explicit, time-boxed, revocable platform-admin access to one tenant |

Tenant-isolation guard: `MigratorTest::testEveryTenantScopedTableHasOrganizationIdAndAnIndexLeadingWithIt` fails if a
new table lacks `organization_id` or a leading index (unless deliberately listed as exempt in the test).

Everything else in the spec (agents, domains, alerts, GA4, ...) arrives with its own ticket.
