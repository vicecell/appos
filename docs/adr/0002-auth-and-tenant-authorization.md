# ADR 0002: Authentication, sessions and tenant authorization (SW-002)

- Status: Proposed (needs manual security review before any production use; see CLAUDE.md workflow step 5)
- Builds on: ADR 0001, spec sections 3, 4, 5

## Decisions

| Concern | Decision | Why |
|---|---|---|
| Password hashing | PHP `password_hash` Argon2id, rehash on login when parameters change | Built in, no dependency |
| MFA | Own RFC 6238 TOTP (SHA1, 6 digits, 30 s, +/-1 step), verified against RFC test vectors; used step is claimed atomically in the DB so a code works once | Small, auditable; avoids a dependency for ~60 lines |
| MFA secret at rest | libsodium secretbox, key derived from `APP_KEY` (HMAC domain separation) | Spec: encrypt secrets at rest |
| Who needs MFA | Platform admins and anyone with an active `client_admin` membership; login is refused (403 `mfa_setup_required`) otherwise | Spec: MFA for privileged access |
| Sessions | Server-side table `sessions`; cookie holds a 256-bit random token, only SHA-256 stored; idle 30 min, absolute 12 h; new token every login; revocable | Revocation requirement; DB leak does not yield sessions |
| Cookie | `HttpOnly; SameSite=Lax; Path=/`, `Secure` + `__Host-` prefix unless `COOKIE_SECURE=false` (local dev only) | Browser-enforced hardening |
| CSRF | Stateless token = HMAC(APP_KEY, session token), required in `X-CSRF-Token` for POST/PUT/PATCH/DELETE on authenticated routes; JSON-only bodies | No extra storage; bound to one session |
| Brute force | Failures counted per account (5) and per IP (20) in a 15-minute window; 429 + `Retry-After`; success clears the account counter | Spec: rate limits |
| Enumeration | Same 401 body for unknown user / wrong password / disabled user; dummy hash verification for timing parity | Standard |
| Tenant authorization | `{tenant}` path segment authorized by the kernel (`Guard`/`Authorizer`) before the handler runs; handlers read `ctx->tenantId()` only. Non-members get **404**, members lacking the permission get **403** | Hides tenant existence; one choke point |
| Tenant scoping in data | Repositories implementing `TenantScoped` take `int $organizationId` first on every public method (reflection test enforces it); child tables use composite FKs `(organization_id, id)` | Cross-tenant bugs become compile-time-ish/test-time failures; DB is the last line |
| Platform admin | `users.is_platform_admin`, **not** a tenant role. No implicit tenant access. Needs a row in `platform_grants` (`read`/`manage`, reason, <=7 days, revocable); every use writes `platform.cross_tenant_access` | Spec: explicit permission for cross-tenant actions |
| Audit | Append-only `audit_logs`; metadata keys that look like secrets are refused. Denied cross-tenant attempts are logged with `organization_id = NULL` so an attacker cannot write into a victim's trail | Spec: audit + no secrets in logs |
| Errors | Unexpected exceptions become a generic 500 (class name + request id logged, never the message) | Messages can contain SQL/hosts/credentials |

## Known limitations (accepted for SW-002, tracked)

- No self-service MFA enrollment, password reset, e-mail verification, or invitations: users are provisioned with `bin/provision.php`.
- `REMOTE_ADDR` is the only client IP source. Behind Nginx this will be the proxy, so per-IP throttling is weak until a trusted-proxy list is added (do this when the Nginx config is finalized).
- Audit rows are append-only by convention (no API), not by database trigger/privilege. Retention purge will need a privileged path.
- SHA-1 TOTP is the universal authenticator-app default; stronger algorithms are poorly supported by clients.
- Sessions are not yet revoked when a user is added to/removed from a tenant (membership is checked per request, so access changes take effect immediately; only the session itself persists).

## Rollback

Each migration `0001`..`0007` has a `.down.sql`; `php bin/migrate.php down` reverts one step. Dropping `users`/`sessions` logs everyone out and deletes accounts, so take a backup before running any `down` outside a development database.
