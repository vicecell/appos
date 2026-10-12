# API

Planned endpoint list: `bahan/ServerWatch_Technical_Specification.md` sections 5 and 16.

Conventions: JSON only (`Content-Type: application/json`, body <= 64 KiB, unknown fields rejected with 422), versioned
`/api/v1`, UTC RFC 3339 timestamps, cursor pagination (`limit` 1-100, `cursor`), structured errors
`{"error":{"code","message","request_id","details?"}}`, every response carries `X-Request-Id`, 429 + `Retry-After`.

Authenticated requests use the session cookie (`sw_session`, or `__Host-sw_session` when secure). State-changing
requests (POST/PUT/PATCH/DELETE) must also send `X-CSRF-Token` (value from login or `/me`).

## Implemented (SW-001, SW-002)

| Method | Path | Auth | Permission | Notes |
|---|---|---|---|---|
| GET | `/health` | none | - | `200 {"status":"ok","service":"serverwatch-dashboard"}` |
| POST | `/api/v1/auth/login` | none | - | body `email`, `password`, `totp_code?`; sets session cookie; returns `user` + `csrf_token` |
| POST | `/api/v1/auth/logout` | session + CSRF | - | revokes the session server-side |
| GET | `/api/v1/me` | session | - | user, `csrf_token`, active `memberships` |
| GET | `/api/v1/tenants/{tenant}/servers` | session | `servers.read` | cursor-paginated |
| POST | `/api/v1/tenants/{tenant}/servers` | session + CSRF | `servers.write` | body `name`, `hostname`; 409 on duplicate hostname in tenant |
| GET | `/api/v1/tenants/{tenant}/servers/{id}` | session | `servers.read` | |
| PATCH | `/api/v1/tenants/{tenant}/servers/{id}` | session + CSRF | `servers.write` | body `name` only |
| GET | `/api/v1/tenants/{tenant}/members` | session | `members.read` | |
| ingestion GET | `/healthz` | none | - | Go ingestion health probe |

### Roles and permissions

| Role | servers.read | servers.write | members.read |
|---|---|---|---|
| viewer | yes | no | no |
| operator | yes | yes | no |
| client_admin | yes | yes | yes |

A platform admin has none of these on any tenant unless an unexpired `platform_grants` row exists
(`read`: read permissions only; `manage`: all).

### Status codes worth knowing

- `401 unauthenticated` no/expired session; `401 invalid_credentials`; `401 mfa_required` (password OK, code missing).
- `403 mfa_setup_required` privileged account without MFA; `403 csrf_invalid`; `403 forbidden` member lacks permission.
- `404 not_found` unknown route, malformed id, **or a tenant/resource the caller may not access** (deliberately indistinguishable).
- `422 validation_failed` with `details` per field; `415`, `413`, `400 invalid_json`; `429 rate_limited`.
