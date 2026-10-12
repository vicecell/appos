# Security and threat model (initial, SW-001)

Living document. Reviewed at the end of every ticket and before any auth or migration change goes to production.

## Assets

Tenant data (servers, alerts, findings), user credentials and sessions, agent credentials, enrollment tokens,
notification channel secrets, GA4 refresh tokens, audit logs.

## Trust boundaries

1. Browser <-> Nginx/PHP dashboard (users, session cookies)
2. Agent (on client host) <-> Nginx/Go ingestion (internet, HTTPS only)
3. Application services <-> MySQL / Redis / VictoriaMetrics (private network)
4. Workers <-> external targets (website/DNS/TLS checks: SSRF surface) and Google APIs

## Threats and planned mitigations (STRIDE, abbreviated)

| # | Threat | Mitigation | Ticket |
|---|---|---|---|
| T1 | Spoofing: forged agent sends data for another tenant | Credential bound to agent_id; tenant/server resolved server-side, payload tenant ignored | SW-003/004 |
| T2 | Spoofing: stolen enrollment token | 15-min TTL, one-time, hashed at rest, file mode 0600, never in URL/CLI/logs | SW-003 |
| T3 | Tampering: replayed or duplicated batches | `batch_id` idempotency window, timestamp skew limit | SW-004 |
| T4 | Information disclosure: cross-tenant read (IDOR) | Tenant-scoped repositories, route-level membership check, automated cross-tenant tests | SW-002+ |
| T5 | Information disclosure: free metric selectors | VictoriaMetrics queries built server-side from user scope only | SW-004 |
| T6 | Information disclosure: secrets in logs | Structured logging with redaction; tests assert no token/password in output | SW-002+ |
| T7 | SSRF via website checks | Block loopback/link-local/metadata/private ranges; re-resolve DNS and re-check redirects | SW-005 |
| T8 | DoS: oversized or high-cardinality payloads | Body size limit, per-agent rate limit, cardinality caps, 429 + Retry-After | SW-004 |
| T9 | Elevation: privilege escalation via role tampering | Server-side RBAC; platform_admin cross-tenant actions require explicit permission and audit | SW-002 |
| T10 | Elevation: agent used as remote shell | No command execution by design; config changes via validated key whitelist only | SW-003 |
| T11 | CSRF / session theft | HTTP-only SameSite cookies, CSRF tokens, MFA for admins | SW-002 |
| T12 | Repudiation | Audit log for every critical change (actor, target, IP, time) | SW-002+ |

## SW-001 controls already in place

- Agent config rejects non-HTTPS URLs, missing host, and embedded credentials (`agent/internal/config`).
- Go HTTP server sets header/read/write/idle timeouts and a header size cap.
- Dev compose binds all ports to 127.0.0.1 and requires secrets from an untracked `.env`.
- systemd unit runs as an unprivileged user with an empty capability set and strict filesystem protection.
- Migrator never drops anything outside a migration's own `.down.sql`; reversibility is enforced.
- CI runs lint/static analysis/tests for all three components.

## SW-002 controls in place (see ADR 0002; pending manual security review)

- T4/T9: tenant authorization happens once in the kernel before any handler; non-members get an indistinguishable 404.
  Tenant-scoped repositories take `organizationId` first (reflection test), composite FKs block cross-tenant
  references in the database (`SchemaTest`). `TenantIsolationTest`/`PlatformAdminTest` cover IDOR, body-supplied tenant
  ids, role-per-tenant, disabled members, suspended orgs, and platform admins without grants.
- Verified by mutation: removing the tenant filter from repositories, giving platform admins implicit access, removing
  the CSRF check, widening the viewer role, skipping the password check and allowing TOTP replay each make tests fail.
- T11: server-side revocable sessions (hashed token), HttpOnly/SameSite cookies, per-session CSRF token, MFA enforced for
  platform admins and client admins, TOTP replay blocked atomically.
- T6/T12: audit log for logins, logouts, server changes, grants and denied cross-tenant attempts; metadata keys that look
  like secrets are rejected; error responses and logs never include exception messages.
- Brute force: per-account and per-IP failure throttling with `Retry-After`; unknown/disabled/wrong-password logins are
  indistinguishable in body and timing.

Not yet covered: password reset, self-service MFA enrollment/recovery, trusted-proxy handling for client IPs, and
database-enforced append-only audit rows (see ADR 0002 "Known limitations").

## Out of scope for now

Production TLS/cert management, secrets manager integration, code signing of agent releases (required before any
production agent rollout per Definition of Done), WAF.
