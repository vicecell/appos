# PHP CLI workers

Planned (not implemented in Sprint 0):

| Script | Sprint | Purpose |
|---|---|---|
| `check_websites.php` | 4 | HTTP/TLS checks with SSRF protection |
| `check_domains.php` | 4 | DNS and RDAP checks |
| `dispatch_alerts.php` | 5 | Alert notification dispatch with retry/DLQ |
| `sync_ga4.php` | 6 | GA4 daily aggregate sync |

Workers will reuse `dashboard/app` classes (tenant-scoped repositories) via the shared Composer autoloader.
