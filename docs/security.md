# Security and threat model (initial, Sprint 0)

Living document. Reviewed at the end of every sprint and before any auth or migration change goes to production.

## Assets

Tenant data (servers, alerts, findings), user credentials and sessions, agent credentials, enrollment tokens,
notification channel secrets, GA4 refresh tokens, audit logs.

## Trust boundaries

1. Browser <-> Nginx/PHP dashboard (users, session cookies)
2. Agent (on client host) <-> Nginx/Go ingestion (internet, HTTPS only)
3. Application services <-> MySQL / Redis / VictoriaMetrics (private network)
4. Workers <-> external targets (website/DNS/TLS checks: SSRF surface) and Google APIs

## Threats and planned mitigations (STRIDE, abbreviated)

| # | Threat | Mitigation | Sprint |
|---|---|---|---|
| T1 | Spoofing: forged agent sends data for another tenant | Credential bound to agent_id; tenant/server resolved server-side, payload tenant ignored | 2-3 |
| T2 | Spoofing: stolen enrollment token | 15-min TTL, one-time, hashed at rest, file mode 0600, never in URL/CLI/logs | 2 |
| T3 | Tampering: replayed or duplicated batches | `batch_id` idempotency window, timestamp skew limit | 3 |
| T4 | Information disclosure: cross-tenant read (IDOR) | Tenant-scoped repositories, route-level membership check, automated cross-tenant tests | 1+ |
| T5 | Information disclosure: free metric selectors | VictoriaMetrics queries built server-side from user scope only | 3 |
| T6 | Information disclosure: secrets in logs | Structured logging with redaction; tests assert no token/password in output | 1+ |
| T7 | SSRF via website checks | Block loopback/link-local/metadata/private ranges; re-resolve DNS and re-check redirects | 4 |
| T8 | DoS: oversized or high-cardinality payloads | Body size limit, per-agent rate limit, cardinality caps, 429 + Retry-After | 3 |
| T9 | Elevation: privilege escalation via role tampering | Server-side RBAC; platform_admin cross-tenant actions require explicit permission and audit | 1 |
| T10 | Elevation: agent used as remote shell | No command execution by design; config changes via validated key whitelist only | 2 |
| T11 | CSRF / session theft | HTTP-only SameSite cookies, CSRF tokens, MFA for admins | 1 |
| T12 | Repudiation | Audit log for every critical change (actor, target, IP, time) | 1+ |

## Sprint 0 controls already in place

- Agent config rejects non-HTTPS URLs, missing host, and embedded credentials (`agent/internal/config`).
- Go HTTP server sets header/read/write/idle timeouts and a header size cap.
- Dev compose binds all ports to 127.0.0.1 and requires secrets from an untracked `.env`.
- systemd unit runs as an unprivileged user with an empty capability set and strict filesystem protection.
- Migrator never drops anything outside a migration's own `.down.sql`; reversibility is enforced.
- CI runs lint/static analysis/tests for all three components.

## Out of scope for now

Production TLS/cert management, secrets manager integration, code signing of agent releases (required before any
production agent rollout per Definition of Done), WAF.
