# Performance benchmarks: V17 indexes + stat caching

Dataset: 100,000 family heads + 2 relatives each = 300,502 member rows,
seeded locally (MariaDB via XAMPP, `php spark serve` on :8090). Timings are
`curl` total time for the full HTML response, logged in as developer.
Supervisor targets: 5-10s full page load, hard cap 30s.

| Page / query | Before (no indexes) | After (V17 indexes + cache) | Target met |
|---|---|---|---|
| Dashboard (first visit) | 0.97s | 0.26s | yes |
| Dashboard (cached stats) | n/a | 0.19s | yes |
| Manage Records list | 0.35s | 0.30s | yes |
| Manage Members list | 0.69s | 0.32s | yes |
| Keyword search (q=Garcia5) | 1.02s | 0.79-0.82s | yes |
| Sectors lookup page | 0.17s | 0.17s | yes |
| Audit trails list | 0.15s | 0.16s | yes |
| Raw: sorted member page (LIMIT 50) | 0.46s | 0.03s | yes |
| Raw: COUNT active members | 0.17s | 0.16s | yes |

Notes:

- The sorted paginated list is the query the composite index
  `idx_member_deleted_name` exists for: 15x faster because the DB reads rows
  already in sort order instead of filesorting 300k rows.
- Keyword search stays a substring LIKE scan by decision (no FULLTEXT), so it
  improves little. At ~0.8s for 300k rows it is comfortably inside target.
- Lookup pages were never DB-bound; their slowness in dev came from the
  single-worker `spark serve` setup (see README dev server section).
- All numbers are server response time. Browser network-tab totals add asset
  loading, which the `.htaccess` cache headers and parallel dev workers
  address; even tripled, every page is far under the 5-10s target.
