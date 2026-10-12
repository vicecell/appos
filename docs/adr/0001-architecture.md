# ADR 0001: Platform architecture and stack

- Status: Accepted (SW-001)
- Source of truth: `bahan/ServerWatch_Technical_Specification.md` (sections 2, 3, 6)

## Context

ServerWatch monitors 20-100 Linux hosts for multiple client organizations (multi-tenant). Telemetry volume
must not block the user-facing dashboard, and a compromised tenant or agent must not reach another tenant's data.

## Decision

| Concern | Choice | Reason |
|---|---|---|
| Dashboard and control-plane REST | PHP 8.3 native + MySQL 8 | Team stack; metadata is relational and low-volume |
| Telemetry ingestion | Go service behind Nginx/TLS | Isolates agent traffic from PHP-FPM; cheap concurrency |
| Host agent | Go, static Linux amd64/arm64 binary, systemd | Small footprint, no runtime dependency |
| Queue | Redis (stream/list) | Decouples ingestion from TSDB writes and alert evaluation |
| Time series | VictoriaMetrics single-node, private network | Prometheus-compatible, low ops cost at this scale |
| Workers | PHP CLI | Reuse tenant-scoped repositories and notifiers |
| Monorepo | `dashboard/ ingestion/ agent/ workers/ deploy/ docs/` | One contract across components; each builds and tests separately |

Key invariants:

1. **Agents are outbound HTTPS only.** No inbound host ports. The agent rejects non-HTTPS server URLs.
2. **tenant_id/server_id are assigned server-side** from the authenticated agent credential, never from the payload.
3. **Every tenant-scoped query carries `organization_id`.** Cross-tenant tests are a gate from SW-002 onward.
4. **MySQL holds metadata only**; raw samples go to VictoriaMetrics.
5. **Migrations are versioned and reversible** (`NNNN_name.up.sql` + `.down.sql`, enforced by `MigrationCatalog`).
6. **No remote command execution, no auto-remediation.**

## Alternatives considered

- *PHP-only ingestion*: rejected; telemetry bursts would compete with dashboard requests for PHP-FPM workers.
- *Metrics in MySQL*: rejected by spec; poor fit for high-write time series and retention/downsampling.
- *Agent pull model (server scrapes hosts)*: rejected; needs inbound ports on client hosts.

## Consequences

- Three runtimes (PHP, Go, Redis/VM) to operate and test; CI covers each separately.
- Contracts between agent, ingestion and dashboard (payload schema, auth) must be versioned (`schema_version`).
- SW-001 ships only health endpoints, config validation and tooling. Auth, tenancy and collectors start in SW-002/SW-003.

## Rollback

SW-001 adds scaffolding only; reverting is deleting the new directories. No production system is touched.
