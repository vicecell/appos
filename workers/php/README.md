# PHP CLI workers

Planned (not implemented in SW-001):

| Script | Ticket | Purpose |
|---|---|---|
| `check_websites.php` | SW-005 | HTTP/TLS checks with SSRF protection |
| `check_domains.php` | SW-005 | DNS and RDAP checks |
| `dispatch_alerts.php` | SW-006 | Alert notification dispatch with retry/DLQ |
| `sync_ga4.php` | SW-012 | GA4 daily aggregate sync |

Workers will reuse `dashboard/app` classes (tenant-scoped repositories) via the shared Composer autoloader.
