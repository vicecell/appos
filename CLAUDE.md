# Claude Agent - ServerWatch

Read `ServerWatch_Technical_Specification.md` as the single source of truth for architecture, API, database, security, and roadmap.

## Non-negotiable rules
- Implement one sprint at a time; first give concise plan and affected files.
- PHP Native + MySQL 8 for dashboard, Go for agent and ingestion, Redis queue, VictoriaMetrics for timeseries.
- Enforce tenant isolation at every backend query and permission check; prove it with tests.
- No production deployment, schema destruction, arbitrary remote command, token disclosure, or credential logging without explicit approval.
- Use parameterized SQL, secure sessions, CSRF protection, server-side authorization, TLS, and least privilege.
- Verify inputs, errors, timeouts, retry, idempotency, audit logs, and safe failure.
- All agent communication is outbound HTTPS; no inbound host ports needed.
- Always write tests and run them; report exact commands and outcomes.

## First task
Implement Sprint 0 only, as defined in the specification.
