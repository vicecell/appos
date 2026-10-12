# CLAUDE.md - ServerWatch Development Rules

> Product specification: `bahan/ServerWatch_Technical_Specification.md` v2.0. **Always read both files** before changes. This file governs how the agent works; the specification defines complete requirements and feature priorities.

## Project identity
- ServerWatch: secure, multi-client infrastructure/website monitoring platform for **20-100 Linux servers**.
- Stack: PHP Native 8.3+ / MySQL 8 web dashboard, Go Linux agent + Go ingestion service, Redis queue, VictoriaMetrics time-series; Nginx, PHP-FPM, optional Loki/OpenTelemetry collector.
- Each tenant owns isolated servers, domains, GA4/GSC connections, alerts, logs, findings, reports and agents.

## Delivery scope and order
1. **P0** multi-tenant core, authentication/RBAC/audit, signed enrollment/identity, host metrics, service checks, website/domain/TLS/DNS/RDAP checks, alerts, operations hardening.
2. **P1** database monitoring, centralized logs, backup health, cron monitoring, Docker health, GA4/GSC, CVE inventory/FIM, SLA and client reports.
3. **P2** OpenTelemetry APM, dependency graphs, external integrations, evidence-based AI incident analyses, capacity forecasting, approved security scanning.
- Do not implement the full feature backlog in one prompt. Never represent P1/P2 features as done when only scaffolding exists.

## Non-negotiable security requirements
- Deny cross-tenant reads/writes at controller, service, repository and metrics/log gateway; test tenants A/B isolation with independent identities and adversarial references.
- Use server-resolved organization identity; never trust tenant/server IDs in agent telemetry. Do not allow unbounded PromQL/LogQL queries from browser clients.
- Prepared statements, safe output encoding, CSRF, strict cookie/session settings, MFA for privileged access, rate limits, immutable or carefully audited security actions.
- Go agents make **outbound-only TLS** connections; one-time enrollment, per-agent rotated/revocable credentials, strict validation and request-body/cardinality limits. Least privilege and bounded offline buffers.
- No arbitrary remote shell, automatic remediation, unsafe active scans, Docker socket exposure or network scanning without explicit authorized scope. Do not deploy to production or run destructive migrations without approval.
- Do not ingest secrets, raw private keys, private file contents or sensitive credentials. Redact logs and spans before export; encrypt OAuth credentials and connection secrets at rest.
- Domain checks and webhooks must be SSRF-safe, including DNS rebinding, redirects, private IP ranges and metadata endpoints.
- AI analyses must be opt-in, based on redacted tenant-scoped evidence, explicitly uncertain, and must not trigger infrastructure actions.

## Development workflow in Zed / Claude Agent
1. Read specification v2; identify current ticket (SW-001 through SW-015 in Section 19).
2. **First propose** short plan: impacted files, dependencies, data migration, acceptance tests, risks and rollback. Wait for approval when work is high impact, production-facing, destructive, or otherwise expressly gated.
3. Implement **one small vertical slice** with sensible names and simple architecture. Keep PHP Native MVC, Go ingestion and Go agent modules independently buildable.
4. Add migrations, tenant-aware indexes and constraints, input validation, API versioning and structured error handling.
5. Write and run automated tests: authentication, authorization, cross-tenant denial, retry/idempotency, limit enforcement and fail-closed behavior.
6. End each ticket with changed-file list, test command/output summary, risks, unfinished work, and next smallest ticket.

## Feature-specific rules
- Database collectors use read-only database accounts; never send DB monitoring passwords to ServerWatch.
- Logs: allowlisted sources, deterministic redaction, retention/quota limits, tenant-scoped queries and audit.
- Backup monitoring checks backup freshness and independently logs restore testing. Do not presume a successful backup means it can be restored.
- Cron monitoring uses unique hashed/rotatable heartbeat secrets and handles missing/late/failed states.
- Docker collection requires secure access isolation and must not grant implicit root-equivalent shell control.
- GA4/GSC APIs use read-only OAuth scopes, encryption, quota-aware jobs, and per-tenant authorization.
- CVE detection must honor distro backports and vendor advisories; present findings with provenance and uncertainty.
- Correlation of website traffic and server failures does not prove causation; label evidence and confidence.

## First instruction when starting a fresh repository
Begin **SW-001 only**. Inspect available files, describe architecture, folder layout, .env.example, Compose development stack, CI/test tools, and threat model. Do not create fake result data or build unrelated advanced features. After the plan is accepted, scaffold and run tests. Do not touch production.

## Key references
- Product spec: `bahan/ServerWatch_Technical_Specification.md`
- OWASP ASVS; Zed ACP; Go security; VictoriaMetrics; Google Analytics Data API; OpenTelemetry semantic conventions.
