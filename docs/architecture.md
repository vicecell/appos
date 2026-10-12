# Architecture

Full description: `bahan/ServerWatch_Technical_Specification.md` section 2. Decisions: `docs/adr/0001-architecture.md`.

```text
Go agent --HTTPS--> Nginx --> Go ingestion --> Redis --> metrics worker --> VictoriaMetrics
                                  |                  \-> alert evaluator --> MySQL --> notifiers
PHP dashboard / REST <--- MySQL + VictoriaMetrics query gateway
PHP CLI workers: website/TLS/DNS/RDAP checks, GA4 sync, alert dispatch
```

Implemented so far (Sprint 0): `dashboard` health endpoint and migration runner, `ingestion` health endpoint,
`agent` config validation, dev compose stack. Nothing else is wired up yet.
