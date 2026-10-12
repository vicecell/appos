# API

Planned endpoint list: `bahan/ServerWatch_Technical_Specification.md` section 5.

Conventions: JSON, versioned `/api/v1`, UTC RFC3339, cursor pagination, structured errors
`{"error":{"code","message","request_id"}}`, 429 + `Retry-After`, idempotency for enrollment and event batches.

## Implemented

| Service | Method | Path | Response |
|---|---|---|---|
| dashboard | GET | `/health` | `200 {"status":"ok","service":"serverwatch-dashboard"}` |
| ingestion | GET | `/healthz` | `200 {"status":"ok","service":"serverwatch-ingest"}` |

Unknown paths return `404` with the structured error body; wrong methods return `405`.
