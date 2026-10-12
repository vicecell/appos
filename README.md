<div align="center">

# ServerWatch

**Multi-tenant infrastructure monitoring, security visibility, and website operations — in one platform.**

Linux servers · Websites & domains · Security posture · Analytics · Incident response

![Status](https://img.shields.io/badge/status-in%20development-orange)
![Backend](https://img.shields.io/badge/backend-PHP%20%2B%20MySQL-777BB4)
![Agent](https://img.shields.io/badge/agent-Go-00ADD8)
![Target](https://img.shields.io/badge/target-Linux-333333)

</div>

> [!IMPORTANT]
> **Project status: early development / pre-release.** ServerWatch is an architecture and implementation project, not yet a production-ready monitoring service. Features below are planned unless explicitly marked as implemented. There are no verified download, deployment, or user adoption figures at this stage.

## Why ServerWatch?

Running infrastructure for multiple clients often means switching between server dashboards, uptime services, domain registrars, log viewers, analytics tools, and security scanners. ServerWatch aims to connect those signals in a single workspace, so small IT teams and managed service providers can detect issues, understand the impact, and respond faster.

**Initial scope:** 20–100 Linux servers across multiple client organizations, with secure agent enrollment, tenant isolation, and practical operational alerts.

## Planned capabilities

| Area | What ServerWatch aims to provide | Priority |
| --- | --- | --- |
| **Linux server monitoring** | CPU, memory, disk, network, uptime, service health, inventory | MVP |
| **Agent management** | Enroll, authenticate, configure, revoke, and monitor Go agents | MVP |
| **Multi-tenant workspaces** | Organizations, membership, role-based access, tenant-scoped assets | MVP |
| **Website and domain monitoring** | HTTP uptime, response times, DNS changes, TLS certificate expiration | MVP |
| **Security baseline** | SSH activity, firewall state, configuration checks, security findings | MVP |
| **Incidents and alerts** | Thresholds, deduplication, acknowledgment, email/Telegram notifications | MVP |
| **Database and service health** | MySQL/MariaDB, PostgreSQL, Redis, PHP-FPM, Nginx, Apache | Phase 2 |
| **Operational checks** | Backups, scheduled jobs, Docker containers, centralized logs | Phase 2 |
| **Website analytics** | Google Analytics 4 and Google Search Console integrations | Phase 2 |
| **Security visibility** | Package inventory, known vulnerabilities, file integrity, patch visibility | Phase 2 |
| **Reporting** | Client health reports, SLA history, incident summaries | Phase 2 |
| **Advanced observability** | Application traces, dependency maps, performance insights | Later |
| **AI-assisted investigation** | Evidence-linked incident hypotheses and capacity forecasting | Later |

The MVP deliberately **excludes** unrestricted remote shell execution, automatic remediation, and scanning third-party networks without authorization.

## Architecture

```text
Client Linux servers (20–100 initially)
  └── Lightweight Go agent (outbound HTTPS only)
        ├── Heartbeat and host metrics
        └── Authorized security / service checks
                        │
                        ▼
                  Nginx + TLS
                        │
                 Go Ingestion API
                  ├── Agent authentication
                  ├── Payload validation
                  └── Tenant / server identity binding
                        │
              Redis queue / workers
                ├── VictoriaMetrics (time-series)
                └── MySQL (metadata, alerts, inventory)
                        ▲
                        │
            PHP Native dashboard + REST API
                ├── Multi-tenant RBAC
                ├── Alerts and incident workflows
                ├── Domain / SSL / HTTP workers
                └── GA4 and external integrations
```

The agent initiates outbound connections; monitored hosts do not need an inbound monitoring port. Metrics identity is assigned by the backend from authenticated agent credentials rather than accepted from client-provided labels.

## Technology stack

| Layer | Planned technology |
| --- | --- |
| Web dashboard / control plane | PHP Native 8.3+, JavaScript, Bootstrap 5, Apache ECharts |
| Application database | MySQL 8 |
| Linux host agent | Go, systemd |
| Telemetry ingestion | Go REST service |
| Historical metrics | VictoriaMetrics |
| Queues and short-lived cache | Redis |
| Gateway | Nginx, HTTPS |
| External integrations | GA4 Data API, Google Search Console, Telegram, email |
| Optional security / observability | Wazuh, OpenTelemetry, Grafana Loki |

## Security by design

- **Tenant isolation:** enforce organization membership and authorization on every application request; verify cross-tenant isolation with automated tests.
- **Agent enrollment:** short-lived one-time enrollment credentials and per-agent identities; support credential revocation and rotation.
- **Transport security:** HTTPS and authenticated telemetry; evaluate mTLS for agent credentials.
- **Least privilege:** minimize agent permissions and avoid arbitrary remote commands in the MVP.
- **Sensitive data:** never transmit secrets in telemetry; redact logs and encrypt third-party tokens at rest.
- **Auditability:** record security-relevant changes, agent enrollments, permission updates, and incident actions.
- **Safe security checks:** passive checks by default; active probing only on explicitly authorized assets.

> Monitoring does not guarantee attack prevention or vulnerability-free systems. Findings and recommendations must be validated before operational action.

## Development roadmap

- [ ] **P0 — Foundation:** repository scaffolding, development setup, database migrations, authentication, multi-tenancy, RBAC, CI and tests.
- [ ] **P0 — Agent & ingestion:** Go Linux agent, enrollment, heartbeat, telemetry ingestion, queue processing, historical metrics.
- [ ] **P0 — Monitoring MVP:** server dashboard, service health, domain/SSL/HTTP checks, alert rules, notifications and audit logs.
- [ ] **P1 — Operations:** database, Docker, backup, cron and centralized log monitoring; maintenance windows and SLA reports.
- [ ] **P1 — Analytics & security:** GA4/Search Console, package vulnerability visibility, file integrity and client reporting.
- [ ] **P2 — Intelligence:** APM, dependency correlation, anomaly detection, explainable AI incident summaries and forecasting.

Roadmap items indicate **planned work**, not completed features or release commitments.

## Repository layout (planned)

```text
serverwatch/
├── dashboard/              # PHP Native dashboard and control-plane API
├── ingestion/              # Go telemetry ingestion service
├── agent/                  # Go Linux monitoring agent
├── workers/                # Scheduled checks and background processors
├── database/               # Migrations and seeders
├── deploy/                 # Docker / systemd / Nginx examples
├── docs/                   # Architecture, API contracts, operational runbooks
├── tests/                  # Integration and security regression tests
├── .env.example            # Safe configuration template (no real secrets)
├── CLAUDE.md               # Development instructions for Claude Agent
└── README.md
```

This is the target structure; individual directories may not exist until the respective implementation sprint.

## Getting started

**There is no public production installation or stable agent release yet.** The repository currently contains SW-001 (scaffold, CI, dev stack) and SW-002 (tenant-aware authentication, RBAC, audit, provisioning CLI; pending manual security review). Agent enrollment, collectors, and telemetry ingestion are not implemented.

For contributors and developers:

1. Review `CLAUDE.md` and the technical specification in `bahan/`.
2. Copy `.env.example` to `.env` and fill in the empty secrets (never commit `.env`).
3. Start the dev stack: `docker compose --env-file .env -f deploy/docker-compose.dev.yml up -d`.
4. Run all quality gates: `sh scripts/check.sh` (needs Docker only; PHP 8.3 and Go run in containers).
5. Enroll **test servers only** until agent security, resource usage, and uninstall procedures are verified.

Details: `docs/runbooks.md`. Architecture decision: `docs/adr/0001-architecture.md`. Threat model: `docs/security.md`.

## Developing with Claude Agent in Zed

The project is designed to support incremental, reviewable AI-assisted development using **Claude Agent in Zed**. The agent instructions live in `CLAUDE.md`.

Suggested first prompt:

```text
Read CLAUDE.md and the ServerWatch technical specification completely.
Implement only the foundation sprint. First propose an implementation plan,
repository structure, database migration approach, security controls, and
acceptance tests. Wait for approval before modifying code. Never deploy to
production or perform destructive actions without explicit authorization.
```

AI-generated changes must be reviewed, tested, and documented before merging.

## Contributing

ServerWatch is currently in the planning and development stage. Architectural discussions, documentation corrections, and well-scoped issue proposals are welcome if the repository enables collaboration.

Before submitting code, please confirm that the relevant component and contribution workflow are available. Favor small pull requests with tests, clear migration steps, and notes about tenant isolation or security impact.

For suspected security vulnerabilities, **do not publish credentials, exploit details, or sensitive server data in a public issue**. Use the repository's private security reporting channel if configured, or contact its maintainers privately.

## Licensing

**License not yet selected.** Until a license is explicitly added to this repository, do not assume permission to copy, redistribute, or deploy the source code under an open-source license. Open-source distribution plans, if any, will be documented here.

## Project vision

Make practical, secure infrastructure monitoring more accessible to small development teams and managed IT providers — starting with Linux servers and evolving toward a unified view of availability, security, application health, and business-impact signals.
