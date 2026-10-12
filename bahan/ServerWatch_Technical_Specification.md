# ServerWatch — Technical Specification & Roadmap

**Versi:** 2.0 (expanded features, 2026-10-12) | **Target:** 20–100 server Linux | **Model:** Multi-client / Multi-tenant | **Backend:** PHP Native + MySQL | **Agent:** Go

## 1. Sasaran dan batasan

ServerWatch adalah platform monitoring terpusat untuk 20–100 host Linux milik sejumlah klien. Setiap organisasi memiliki workspace, pengguna, asset, alert, konfigurasi, dan integrasi GA4 sendiri. Platform Admin boleh mengelola sistem lintas tenant, sementara Client Admin, Operator, dan Viewer hanya mengakses tenant terkait.

**MVP termasuk:** onboarding tenant, enrollment agent, CPU/RAM/disk/network, status service, grafik historis, uptime website, TLS expiry, DNS, basic SSH/firewall checks, alert email/Telegram, audit logs, RBAC, dan dashboard responsif.

**Tahap berikutnya:** monitoring database, centralized logs, backup/cron health, Docker, Google Analytics GA4 OAuth, Google Search Console, inventory software/CVE, file integrity monitoring, advanced incident workflow, laporan, dan integrasi Wazuh. Tidak termasuk remote shell, auto-remediation, antivirus penuh, dan auto-scan jaringan tanpa izin.

## 2. Arsitektur teknis

```text
Linux Host A/B/...N
  Go Agent (systemd; outbound HTTPS only)
     -> POST /api/v1/agent/heartbeat
     -> POST /api/v1/agent/metrics
     -> POST /api/v1/agent/events
                   |
              Nginx / TLS
                   |
             Go Ingestion API ----> Redis Stream / queue
                   |                        |
              Auth + validation       Metrics worker -> VictoriaMetrics
                   |                        |
              MySQL metadata       Alert evaluator -> MySQL -> Notifiers
                   |
PHP Native dashboard / REST API <--- MySQL + VictoriaMetrics query gateway
       |
 PHP CLI workers: HTTP/TLS/DNS/RDAP, GA4, alert dispatch
```

Komponen ingestion dan UI dipisah agar trafik telemetry tidak memblokir PHP-FPM. Gunakan satu VictoriaMetrics single-node dalam jaringan privat pada MVP; bukan endpoint publik yang dapat diakses agent. Semua metrik wajib diberi tenant_id dan server_id oleh ingestion service berdasarkan credential agent yang tervalidasi, bukan dipercaya dari payload klien.

**Sizing awal (perkiraan untuk pilot):** VM aplikasi 4 vCPU/8 GB, MySQL 2–4 vCPU/4–8 GB, VictoriaMetrics 2–4 vCPU/4–8 GB dan SSD. Dapat digabung pada fase laboratorium, tetapi pisahkan saat produksi. Validasi kapasitas melalui load test 100 agents, bukan menjadikan angka ini jaminan kapasitas.

## 3. Multi-tenancy dan RBAC

**Role:** platform_admin, client_admin, operator, viewer. Platform admin bukan role tenant biasa. Semua request user memastikan membership tenant aktif dan izin spesifik aksi; tenant_id tidak boleh dipilih bebas lewat body request. Gunakan route /api/v1/tenants/{tenant_id}/... dengan authorization middleware, serta repository yang selalu memakai tenant scope.

**Isolasi:** tenant_id sebagai FK dan indeks komposit pada seluruh record tenant-scoped; foreign key komposit atau validasi eksplisit mencegah referensi antar-tenant; object storage dan secrets dipisahkan secara logis. VictoriaMetrics memakai label tenant_id + server_id, tetapi query selalu dibangun server-side dari scope user, tidak menerima selector bebas dari browser. Automated cross-tenant access tests wajib tersedia.

## 4. Rancangan database MySQL

**Identitas & tenancy:** organizations(id, name, slug, status); users(id, name, email, password_hash, mfa_enabled, status); organization_members(id, organization_id, user_id, role, status); invitations(id, organization_id, email, role, expires_at, token_hash).

**Agent & server:** servers(id, organization_id, name, hostname, os, kernel, status, last_seen_at, tags_json); agents(id, organization_id, server_id, agent_uuid, version, status, cert_fingerprint, credential_revoked_at); enrollment_tokens(id, organization_id, token_hash, expires_at, used_at, max_uses); agent_configs(id, organization_id, server_id, version, settings_json); server_services(id, organization_id, server_id, service_name, last_state); server_inventory(id, organization_id, server_id, snapshot_json, collected_at).

**Domain/website:** domains(id, organization_id, fqdn, registrar, expires_at, check_enabled); websites(id, organization_id, server_id nullable, domain_id nullable, url, expected_status, enabled); website_checks(id, organization_id, website_id, checked_at, http_status, latency_ms, success, error_code); tls_checks(id, organization_id, domain_id, checked_at, valid_to, chain_valid); dns_observations(id, organization_id, domain_id, record_type, records_hash, checked_at).

**Analytics:** ga_connections(id, organization_id, connected_by, encrypted_refresh_token, status); ga_properties(id, organization_id, connection_id, property_id, display_name); ga_daily_metrics(id, organization_id, property_id, report_date, active_users, sessions, views, engagement_rate, updated_at). Simpan data agregat, bukan semua data mentah GA4.

**Security:** security_findings(id, organization_id, server_id, rule_id, severity, status, title, evidence_json, first_seen_at, last_seen_at); security_events(id, organization_id, server_id, kind, occurred_at, attributes_json); software_inventory(id, organization_id, server_id, package_name, version, collected_at); vulnerabilities(id, organization_id, server_id, cve_id, package_name, severity, status, detected_at); file_integrity_events(id, organization_id, server_id, path_hash, change_type, detected_at).

**Alerting & audit:** alert_rules(id, organization_id, type, conditions_json, severity, cooldown_sec, enabled); alerts(id, organization_id, server_id nullable, rule_id, fingerprint, status, triggered_at, resolved_at); alert_events(id, organization_id, alert_id, event_type, actor_id nullable, occurred_at); notification_channels(id, organization_id, type, encrypted_config, enabled); notification_deliveries(id, organization_id, alert_id, channel_id, status, attempts, next_retry_at); audit_logs(id, organization_id nullable, actor_id nullable, action, target_type, target_id, ip_address, occurred_at).

**Constraint penting:** UNIQUE(users.email), UNIQUE(organizations.slug), UNIQUE(agents.agent_uuid), UNIQUE(ga_properties.organization_id, property_id); indeks (organization_id, server_id), (organization_id, status), (organization_id, checked_at), (organization_id, occurred_at). Gunakan BIGINT UNSIGNED, UTC DATETIME(3), InnoDB, utf8mb4, migrasi versioned. Jangan taruh sampel metrik mentah di MySQL.

## 5. REST API — control plane dan agent

| Method | Path | Keterangan |
|---|---|---|
| POST | /api/v1/auth/login | Session + CSRF, MFA untuk admin |
| GET | /api/v1/me | Identity dan tenant memberships |
| GET/POST | /api/v1/tenants/{tenant}/servers | List/add server; RBAC |
| GET | /api/v1/tenants/{tenant}/servers/{id}/metrics | Time-range aggregates dari metrics query gateway |
| POST | /api/v1/tenants/{tenant}/agents/enrollments | Buat token sekali pakai; Client Admin |
| GET | /api/v1/tenants/{tenant}/domains | Domain list |
| POST | /api/v1/tenants/{tenant}/websites | Tambah website check; SSRF protection |
| GET | /api/v1/tenants/{tenant}/alerts | Daftar alert ter-scope |
| POST | /api/v1/tenants/{tenant}/alerts/{id}/acknowledge | Acknowledge dengan audit |
| GET | /api/v1/tenants/{tenant}/security/findings | Security findings |
| GET | /api/v1/tenants/{tenant}/ga/properties | GA4 property list |
| GET | /api/v1/tenants/{tenant}/ga/callback | OAuth callback state-bound (rute konkret bisa global) |
| POST | /api/v1/agent/enroll | Exchange token menjadi agent identity |
| POST | /api/v1/agent/heartbeat | Agent heartbeat |
| POST | /api/v1/agent/metrics | Batching telemetry |
| POST | /api/v1/agent/events | Batching security events |
| GET | /api/v1/agent/config | Konfigurasi signed/validated |

**API convention:** JSON; versioned endpoints; pagination cursor/limit; UTC RFC3339; structured errors {error:{code,message,request_id}}; idempotency untuk enrollment dan event batches; 429 Retry-After; body size limit; per-agent rate limit. UI memakai secure HTTP-only same-site cookies dan CSRF; agent memakai identity credential mTLS atau short-lived signed tokens terikat pada agent_id. API publik harus memvalidasi otorisasi setiap resource.

### Contoh payload metrics

```json
{
  "schema_version": 1,
  "batch_id": "uuid-v4",
  "captured_at": "2026-10-12T06:00:00Z",
  "interval_seconds": 30,
  "system": {"cpu_usage_percent": 23.5, "memory_used_bytes": 1480000000},
  "filesystems": [{"mount": "/", "used_percent": 61.2}],
  "network": [{"interface": "eth0", "rx_bytes_total": 1024000, "tx_bytes_total": 850000}]
}
```

Agent tidak menyertakan tenant_id sebagai otoritas. Server memberi tenant_id/server_id setelah autentikasi. Endpoint metrics harus menolak timestamp terlalu jauh dari waktu sistem, metrik tanpa batas kardinalitas, batch terlalu besar, dan duplicate batch_id (idempotency window).

## 6. Desain Go Agent

**Paket:** cmd/serverwatch-agent, internal/collector/{cpu,memory,disk,network,systemd,ssh,firewall,packages}, internal/transport, internal/buffer, internal/config, internal/update, internal/health. Build Linux amd64 dan arm64; gunakan systemd unit; log ke journald; privilege minimal.

**Loop:** heartbeat 30 detik; CPU/RAM/network 30 detik; disk/service 60 detik; inventory 6–24 jam; security events 30–60 detik atau tail event; jitter +/- 10%; exponential backoff. Disk buffer terenkripsi/permission-restricted dengan batas 50–100 MB dan kebijakan drop oldest untuk telemetry non-kritis. Queue events penting diprioritaskan.

**Enrollment:** admin buat token dengan TTL 15 menit, one-time; agent install dengan token via environment/file mode 0600; HTTPS terverifikasi; API menukar token menjadi identitas unik; credential disimpan permission 0600; rotasi, revocation, audit. Jangan meletakkan enrollment token dalam CLI shell history atau URL log. Install script harus signed/checksummed. Endpoint agent hanya menerima outbound koneksi 443.

**Security posture:** awalnya deteksi SSH failed logins, systemd service anomalies, firewall status, port listening changes, pending critical patches; FIM pada beberapa file konfigurasi yang disepakati. Jangan upload isi file, SSH private key, password, atau full journal secara default. Perubahan konfigurasi agent hanya melalui whitelist key yang tervalidasi. Tidak ada arbitrary remote command execution.

## 7. Domain, GA4, dan security service

**Website workers:** HTTP status/latency tiap 60 detik, TLS validity tiap 12 jam, DNS setiap 15 menit, RDAP tiap 24 jam (jika tersedia). Terapkan SSRF protections: blok loopback, link-local, metadata IP, private network kecuali secara eksplisit diizinkan; DNS re-resolution/redirect diperiksa; validasi hostname dan timeout. Beberapa lokasi probe untuk mengurangi false positives ketika skala bertambah.

**GA4:** OAuth 2.0 server-side read-only (analytics.readonly), state + PKCE bila sesuai client, refresh token dienkripsi menggunakan managed key. Property access diverifikasi, sync agregat daily hourly/periodic, realtime hanya bila perlu. Quota, 429, dan retry backoff harus diimplementasikan. Referensi: developers.google.com/analytics/devguides/reporting/data/v1/quotas.

**Security:** temuan memiliki evidence, first_seen, last_seen, severity, false-positive/acknowledge workflow; CVE matching diprioritaskan pada package + versi OS/vendor advisory, bukan hanya string version naïf. Wazuh opsional dipakai untuk fungsi kompleks, jangan duplikasi penuh security scanner pada MVP.

## 8. Alert, SLA, dan retention

**Default rules:** host no heartbeat >90 detik dengan 2 konfirmasi; CPU >90% selama 5 menit; RAM available <10% selama 5 menit; disk >85% warning, >95% critical; website HTTP down dua kali berturut-turut; SSL <30/14/7 hari; SSH failures threshold configurable. Gunakan alert fingerprint dan state machine open -> acknowledged -> resolved; cooldown; maintenance windows; notif retry with dead-letter queue.

**Retention awal:** raw metrics 30 hari, downsample hourly 12 bulan, website-check detailed 90 hari, security events 90 hari (perjanjian dan regulasi dapat mengubah), audit logs 12 bulan, GA4 aggregates 13 bulan; ada tenant-specific policy dan mekanisme deletion/export. Backup MySQL daily + PITR bila memungkinkan; metrics snapshots; restore drill per kuartal.

**Target pilot (bukan SLA kontraktual):** 100 host; ingestion success >=99.5% pada jaringan normal; p95 dashboard API <500 ms untuk query ringkas; median heartbeat visibility <60 detik; alert <=2 menit setelah threshold terpenuhi; tidak ada akses lintas tenant dalam pengujian.

## 9. Struktur repository Zed/Claude Agent

```text
serverwatch/
  CLAUDE.md
  README.md
  docs/{architecture.md,api.md,data-model.md,security.md,runbooks.md}
  dashboard/
    public/index.php
    app/{Controllers,Models,Services,Middleware,Repositories,Views,Core}/
    config/ routes/ database/migrations/ tests/
    composer.json .env.example
  ingestion/
    cmd/serverwatch-ingest/main.go
    internal/{http,auth,validation,queue,metrics}/
    go.mod
  agent/
    cmd/serverwatch-agent/main.go
    internal/{collectors,transport,buffer,config,security}/
    packaging/systemd/
    go.mod
  workers/
    php/{check_websites.php,check_domains.php,sync_ga4.php,dispatch_alerts.php}
  deploy/
    nginx/ systemd/ docker-compose.dev.yml
  .github/workflows/
```

Simpan secrets di .env yang tidak di-commit, dan konfigurasi default non-secret di .env.example. Repository tunggal (monorepo) membantu Claude Agent memahami kontrak di antara dashboard, ingestion, dan agent; compile dan unit test masing-masing komponen terpisah.

## 10. Roadmap implementasi

Roadmap resmi dan urutan rilis terdapat pada **Section 18** (P0: MVP / P1: operational enhancements / P2: advanced intelligence). Estimasi 12 minggu hanya berlaku untuk pilot MVP sesuai sumber daya dan hasil pengujian.

## 11. Definition of Done per modul

Setiap modul dianggap selesai hanya jika: migrations reversible; authz + tenant isolation diuji; input validation; unit/integration tests lulus; no secrets in logs; audit bila ada perubahan kritis; health metrics; dokumentasi endpoint; UI responsive; errors jelas; serta rollback plan. Agent baru boleh masuk server produksi setelah code-signing/checksum, load test overhead, dan prosedur uninstall diuji.

## 12. Instruksi kerja Claude Agent di Zed

1. Simpan CLAUDE.md di root repository, buka folder serverwatch/ di Zed, lalu jalankan Claude Agent melalui External Agents/ACP.
2. Minta Claude membaca CLAUDE.md dan membuat plan per sprint, tanpa mengubah semua komponen sekaligus.
3. Mulai Sprint 0: bootstrap folder, .env.example, Docker Compose development, linting, migrations skeleton, test runners dan README.
4. Lanjutkan satu issue/PR dalam satu thread atau unit kerja. Selalu minta daftar file berubah, command test, bukti hasil, dan risiko.
5. Sebelum setiap migration production dan perubahan auth, lakukan code review manual.

**Prompt awal untuk Claude Agent:**

```text
Read CLAUDE.md thoroughly. You are the lead engineer for ServerWatch, a multi-tenant monitoring platform (PHP Native/MySQL dashboard, Go ingestion, Go Linux agent, VictoriaMetrics, Redis). Implement only Sprint 0. First propose an architecture decision record and file plan; then scaffold the monorepo, development environment, baseline lint/tests, and README. Do not execute destructive commands, expose secrets, or deploy to production. Run available tests and report exact commands, changed files, results, risks, and the next smallest task.
```

**Prompt Sprint 1:**

```text
Following CLAUDE.md, implement tenant-aware authentication, organizations, memberships, roles, policies, and MySQL migrations in dashboard/. Start with tests proving a user from tenant A cannot read or modify tenant B resources. Require explicit permission for platform-admin cross-tenant actions. Stop after Sprint 1 and summarize migration and rollback steps.
```

## 13. Ekspansi produk: daftar modul dan prioritas

Dokumen ini menggantikan baseline v1 dan membedakan fitur **MVP**, **P1**, dan **P2**. Tidak semua fitur adalah komitmen rilis 12 minggu. Gate tiap fase bersifat acceptance-driven.

| Modul | MVP (P0) | P1 | P2 |
|---|---|---|---|
| Server | CPU, RAM, disk, network, service, heartbeat | MySQL/MariaDB/Postgres health, Docker containers | Kubernetes |
| Website/domain | HTTP uptime, latency, DNS/TLS, RDAP | Core Web Vitals synthetic, multi-region probes | RUM / advanced UX |
| Observability | host metrics, incident timeline | Log ingestion/search, OpenTelemetry PHP APM | Distributed tracing dependency graph |
| Resilience | alerting, backup platform itself | Tenant backup-monitoring, cron heartbeat | Capacity forecasting |
| Security | SSH failures, firewall, open ports, security baseline | CVE inventory, file integrity, patch visibility, TLS audit | Wazuh integration, compliance reports, approved scans |
| Analytics | — | GA4, Search Console, server-to-website correlation | Automated insights |
| Multi-client | tenant isolation, RBAC, audit | SLA reports, reports export, webhooks, branding | white-label / customer portal |
| Intelligence | deterministic alerts | dependency mapping | AI incident hypotheses, anomaly detection |

**P0 guardrails:** P1/P2 features may have navigation placeholders only if clearly marked “coming later”; no bogus metrics. No auto-remediation or general-purpose remote commands from the agent.

## 14. Expanded module specifications

### 14.1 Database monitoring

- MySQL/MariaDB MVP extension: connections, threads running, uptime, database size, slow query count/rate, InnoDB lock waits, optional replication lag.
- PostgreSQL: sessions, conflicts, deadlocks, replication lag, database size.
- Collect only using dedicated read-only monitoring accounts; credentials stay on the monitored host, are never transmitted, and should be delivered through OS secret storage or root-readable config. Never record query values or personally identifiable content by default.
- Prefer counters and derived rates; bound label cardinality (do not label individual SQL statements or end users). Version compatibility matrix and metrics permission tests required.

### 14.2 Centralized logs

- Initial adapters: journald, Nginx/Apache error log, PHP-FPM, MySQL. Optional Loki store behind a tenant-scoped query proxy; MySQL only stores log source configuration and ingestion status.
- Default structured event fields: timestamp, tenant/server identity added by backend, source, level, message (redacted), trace_id if present.
- Regex/structured redaction of tokens, passwords, cookies and sensitive query parameters before transmission; allowlist log paths; bounded queue, backpressure, rotation handling, retention and access audit.
- Never allow browser-supplied unrestricted Loki selectors; enforce tenant scope in query service. Protect against log injection and high cardinality.

### 14.3 Backups, cron and scheduled jobs

- Register backup jobs with expected schedule, last success time, artifact age/size/checksum, verification status, and last restore-test timestamp. Backup success does not imply restore success.
- Cron checks via per-job unique heartbeat URL/token or local agent job-status collector; distinguish STARTED, SUCCEEDED, FAILED, MISSING and LATE. Tokens must be hashed and rotatable; apply rate limit.
- Alert on missing heartbeat, excessive runtime, failed exit status and stale backups; support maintenance windows.

### 14.4 Containers, APM and performance

- Docker container inventory includes name/ID, image digest, health, restart count and CPU/memory; socket access is highly privileged, so default to separate least-privilege collector/proxy rather than mounting unrestricted Docker socket into dashboard or agent.
- APM through OpenTelemetry instrumented applications, ingested by an OpenTelemetry Collector, not by invasive host agent hooks. Capture request durations and error rates; sanitize spans and attributes.
- Synthetic website performance: TTFB, Lighthouse lab scores and field-based CWV (LCP, INP, CLS) where trustworthy sources are available. Differentiate lab and field data; no false precision.
- Network checks: TCP reachability, packet loss and TLS handshakes, with private/internal endpoints explicitly permitted only for verified tenant-owned agents.

### 14.5 Dependency map and correlation

- A graph stores relationships website -> domain -> server -> service -> database/container, plus deployments and relevant third-party integrations.
- Correlate timestamps across HTTP incidents, resource spikes, app logs, GA4 traffic and deployment events; present evidence and confidence; correlation is **not** proof of causation.
- Include tenant-scoped manual overrides and provenance for every inferred edge.

### 14.6 AI insights (P2)

- AI analyzes restricted, redacted incident summaries and metrics aggregates; default opt-in at tenant level with explicit data processing notice.
- Generated output must include observed evidence, competing explanations, confidence/uncertainty and suggested human validation steps; never claim root cause without substantiation.
- Do not pass credentials, raw personal logs, or cross-tenant context to AI providers. No automatic server changes or shell execution based on AI recommendations.

### 14.7 Expanded security

- CVE checks should use distribution/vendor security advisories and installed package release identifiers; account for backported fixes and false positives.
- File Integrity Monitoring defaults to a defined allowlist of sensitive config paths and hashes/metadata only, with no private-key contents; manage high-churn paths carefully.
- Optional ClamAV/Wazuh security integrations use explicit tenant permission. Active scans require verified ownership, scoped targets, safe rate limits, scheduling, and approval.
- Security score and health score are transparent weighted summaries with evidence and explanatory caveats, **not certifications or guarantees**.

### 14.8 External integrations

- GA4: OAuth read-only, quota-aware workers, aggregates per property; Google Search Console: verified property, impressions/clicks/CTR/position, restricted scopes.
- Cloudflare: DNS and WAF summaries via least-privilege tokens scoped to verified zones; GitHub/GitLab: deployment webhooks and commits with signature validation.
- Notifications: Telegram, email, Slack/Discord and generic signed webhooks; provider outages must not block ingestion.
- Reports: per-tenant weekly/monthly PDF exports for uptime, SLA, incidents, backups and security posture; timestamps and report provenance included.

## 15. Expanded database schema and tenancy guarantees

These are **additional** MySQL relational tables beyond Section 4. Every tenant-owned row MUST include `organization_id` with tenant-aware indexes and cross-tenant relation constraints; IDs are examples and are not SQL migrations.

| Domain | Tables / critical columns |
|---|---|
| Database monitoring | `database_instances` (organization_id, server_id, engine, version, enabled); `database_monitoring_configs` (instance_id, credential_reference, interval_sec) |
| Logging | `log_sources` (organization_id, server_id, type, path_allowlist_json, enabled); `log_ingestion_status` (source_id, cursor_ref, last_seen_at, dropped_count) |
| Backups | `backup_jobs` (organization_id, server_id, expected_schedule, tolerance_sec); `backup_runs` (job_id, started_at, finished_at, status, artifact_age_sec, verified_at); `restore_tests` (backup_job_id, tested_at, result, notes) |
| Cron | `scheduled_jobs` (organization_id, server_id, job_key, expected_schedule, token_hash); `job_runs` (scheduled_job_id, started_at, ended_at, exit_code, state) |
| Docker | `container_inventory` (organization_id, server_id, container_id, image_digest, health, restart_count, collected_at) |
| Correlation | `applications` (organization_id, name); `asset_dependencies` (organization_id, source_type, source_id, target_type, target_id, provenance); `deployments` (organization_id, application_id, commit_sha, deployed_at, source) |
| Performance | `synthetic_checks` (organization_id, website_id, run_at, ttfb_ms, lcp_ms, cls, inp_ms, measurement_type) |
| Search Console | `gsc_connections` (organization_id, encrypted_refresh_token, status); `gsc_properties` (organization_id, connection_id, site_url); `gsc_daily_metrics` (organization_id, property_id, day, clicks, impressions, ctr, position) |
| Client reporting | `report_schedules` (organization_id, format, frequency, recipients_json); `report_exports` (organization_id, report_type, status, storage_key, generated_at) |
| Integrations | `integration_connections` (organization_id, provider, encrypted_config_ref, status); `incoming_webhook_events` (organization_id, provider, event_id, received_at, processing_status) |
| Operations | `maintenance_windows` (organization_id, starts_at, ends_at, scope_json); `sla_policies` (organization_id, target_percent, evaluation_window); `agent_update_rollouts` (organization_id, version, rollout_state, approved_by) |
| AI and governance | `ai_analysis_runs` (organization_id, incident_id, model_ref, evidence_refs_json, state); `data_retention_policies` (organization_id, dataset_type, retention_days); `tenant_exports` (organization_id, requested_by, status) |

Metric values, log messages and traces live in dedicated observability stores; do not put high-volume raw telemetry into MySQL. Enforce authorization in both SQL and query gateways. Consider `organization_id` in uniqueness constraints and composite foreign keys; test tenant A references to tenant B objects.

## 16. Expanded REST API contract

All management endpoints inherit `/api/v1/tenants/{tenant}` and require session/API identity, scoped authorization, pagination, validation and audit where applicable.

| Method | Relative path | Function |
|---|---|---|
| GET | `/databases` | Instances and latest status |
| POST | `/databases` | Register read-only monitoring target |
| GET | `/databases/{id}/metrics` | Time-series metrics |
| GET | `/logs/search` | Tenant-filtered search with bounded time range |
| POST | `/log-sources` | Add source with path allowlist |
| GET | `/backups/jobs` | Backup health |
| POST | `/backups/jobs` | Define monitored backup job |
| GET | `/scheduled-jobs` | Cron monitor health |
| POST | `/scheduled-jobs` | Register monitored job and issue heartbeat secret |
| POST | `/scheduled-jobs/{id}/rotate-token` | Rotate job secret |
| GET | `/containers` | Docker inventory |
| GET | `/applications/{id}/dependencies` | Tenant-restricted dependency graph |
| GET | `/performance/websites/{id}` | Synthetic web metrics |
| GET | `/gsc/properties` | Connected Search Console properties |
| GET | `/integrations` | Integration status, no plaintext secrets |
| POST | `/integrations/{provider}/connect` | Scoped authorization start |
| GET | `/reports` | Tenant reports and export status |
| POST | `/reports/generate` | Queue report generation |
| GET | `/sla` | Calculated service availability |
| POST | `/maintenance-windows` | Scheduled suppression |
| GET | `/incidents/{id}/timeline` | Evidence from metrics/logs/deployments |
| POST | `/incidents/{id}/analyze` | Opt-in AI analysis, P2 only |

New agent endpoints: `POST /api/v1/agent/logs` (opt-in redacted events), `POST /api/v1/agent/inventory`, `POST /api/v1/agent/job-events`; exact schemas require version negotiation, strict size ceilings, idempotent `batch_id`, optional compression, and signature/auth validation. Agent collects *only* enabled capabilities; server decides tenant/server identity. A heartbeat endpoint for cron jobs uses per-job secret and responds generically to avoid account enumeration.

## 17. Production operations, capacity and observability

- At 100 hosts transmitting one telemetry batch every 15 seconds, expect ~576,000 batches/day before logs and security events; model peak bursts and failures, not averages only.
- Metric retention baseline: raw 30 days, hourly downsample 12 months. Logs 14–30 days initially, configurable per tenant; high-priority security events retention by contract. Make storage/ingest limits explicit.
- Enforce per-tenant quotas for agents, sites, metrics cardinality, log bytes, API requests, GA4 calls, reports and AI spend. Reject excess safely with observability of dropped data.
- Monitor ServerWatch itself: ingestion availability, buffer saturation, queue lag, TSDB disk, worker freshness, failed domain probes, token refresh, alert delivery lag and backup freshness.
- Disaster recovery: encrypted offsite backups, restore tests, key rotation, retention purge, tenant export/deletion, documented RPO/RTO.
- Avoid Docker socket exposure, generic remote shell, unsigned automatic updates, user-supplied PromQL/LogQL without server-side scoping, and unrestricted SSRF via webhook or website checks.

## 18. Updated delivery roadmap (phased, no deceptive deadline commitments)

| Phase | Indicative timing | Scope | Acceptance gates |
|---|---|---|---|
| P0 foundation | Weeks 1–3 | Monorepo, threat model, tenancy/RBAC, audit, tests | Cross-tenant and security tests pass |
| P0 agent + telemetry | Weeks 4–6 | Go enrollment, heartbeat, host metrics, VictoriaMetrics, Redis | 5 pilot hosts reliable; idempotent ingest |
| P0 web + alerting | Weeks 7–9 | HTTP/TLS/DNS/RDAP, services, alert notification, dashboard | Alert dedupe, SSRF tests, audit verified |
| P0 pilot hardening | Weeks 10–12 | Load 100 agents, resilience, docs, restore drill | Performance and privacy/security review |
| P1 operations | After pilot (4–8+ weeks) | Database, logs, backup, cron, Docker, GA4/GSC, CVE, reporting | Per-module integration/security tests |
| P2 intelligence | Later | APM, dependency graphs, Cloudflare/GitHub, AI, advanced scans | Explicit opt-in, privacy controls, validation |

**Note:** Timing bergantung pada kapasitas tim, infrastruktur, dan compliance; gunakan gate berdasarkan acceptance criteria, bukan tanggal semata.

## 19. Claude Agent work packages and prompts

Build one bounded vertical slice at a time. Suggested ticket order:

1. `SW-001` Monorepo structure, secure configuration, local dev/CI.
2. `SW-002` Tenant-aware users, RBAC and cross-tenant tests.
3. `SW-003` Agent enrollment + credential rotation/revocation.
4. `SW-004` Host metrics ingestion and VictoriaMetrics query gateway.
5. `SW-005` HTTP/TLS/DNS/RDAP checks and SSRF defense tests.
6. `SW-006` Alert state machine, Telegram/email and maintenance windows.
7. `SW-007` 100-agent load tests and backup/restore exercises.
8. `SW-008` MySQL/MariaDB database monitoring (least-privilege).
9. `SW-009` Cron/backup heartbeat monitor.
10. `SW-010` Centralized structured logs and redaction.
11. `SW-011` Docker monitoring with privileged socket isolation.
12. `SW-012` GA4 and Search Console read-only integration.
13. `SW-013` CVE/vendor advisory and file integrity monitoring.
14. `SW-014` Reporting, SLA, correlation timeline and integrations.
15. `SW-015` APM, dependency graph and human-reviewed AI hypotheses.

For each ticket: write acceptance criteria first; implement minimal migrations/API/UI/agent changes; test normal cases and cross-tenant denial, failures, retries and unauthorized access; document commands and rollback. Use feature flags for post-MVP features.

**Prompt for next Zed conversation:**

```text
Read CLAUDE.md and ServerWatch_Technical_Specification.md v2 in full. Treat the technical specification as the product scope and CLAUDE.md as your execution rules. First propose an implementation plan for SW-001 only with files, security tests, CI and acceptance criteria. Do not implement P1/P2 modules yet. Avoid production changes, remote arbitrary shell execution, unsafe scanning, secrets in logs, or cross-tenant queries. After explicit approval implement and run tests, then report results and blockers.
```

## 20. Referensi implementasi

- Zed External Agents / ACP: https://zed.dev/docs/ai/external-agents
- GA4 Data API quotas: https://developers.google.com/analytics/devguides/reporting/data/v1/quotas
- VictoriaMetrics Prometheus integration: https://docs.victoriametrics.com/victoriametrics/integrations/prometheus/
- OWASP ASVS: https://owasp.org/www-project-application-security-verification-standard/
- Wazuh documentation: https://documentation.wazuh.com/
