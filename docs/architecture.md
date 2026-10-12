# Architecture

Full description: `bahan/ServerWatch_Technical_Specification.md` section 2. Decisions: `docs/adr/0001-architecture.md`.

```text
Go agent --HTTPS--> Nginx --> Go ingestion --> Redis --> metrics worker --> VictoriaMetrics
                                  |                  \-> alert evaluator --> MySQL --> notifiers
PHP dashboard / REST <--- MySQL + VictoriaMetrics query gateway
PHP CLI workers: website/TLS/DNS/RDAP checks, GA4 sync, alert dispatch
```

Implemented so far (SW-001, SW-002):

- `dashboard`: health endpoint, reversible migration runner, login with sessions/CSRF/TOTP MFA, RBAC and
  tenant authorization (kernel-level), tenant-scoped server and member APIs, audit log, provisioning CLI.
- `ingestion`: health endpoint. `agent`: HTTPS-only config validation.
- Dev compose stack (MySQL 8, Redis, VictoriaMetrics).

Nothing else is wired up yet (agent enrollment, telemetry, checks and alerts start with SW-003).
