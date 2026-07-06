# Version Pins & Canonical Sources

Freshness anchor for `docs/knowledge/`. On a dependency bump, refresh the
affected cheatsheets and update this file.

## Pins (source of truth in parentheses)

| Dependency    | Pinned version | Source of truth                                          |
|---------------|----------------|----------------------------------------------------------|
| CodeIgniter 4 | v4.7.3         | `composer.lock` (`codeigniter4/framework`)               |
| PHP           | 8.2.30         | local runtime; repo floor is PHP 8.2+ (CLAUDE.md)        |
| Bootstrap     | v5.3.3         | `public/assets/bootstrap/css/bootstrap.min.css` (header) |
| UI theme      | SB Admin 1 (startbootstrap-sb-admin v7+) | design decision, spec 2026-07-06 |

## Canonical doc URLs

- CodeIgniter 4 user guide: https://codeigniter.com/user_guide/
- Bootstrap 5.3: https://getbootstrap.com/docs/5.3/
- SB Admin 1: https://startbootstrap.com/template/sb-admin
- PHP manual: https://www.php.net/manual/en/

## Context7 (live framework docs)

- CodeIgniter 4: `/codeigniter4/userguide` (latest docs — no version-pinned ID)
- Bootstrap 5.3: `/websites/getbootstrap_5_3` (version-pinned, matches the 5.3.3 vendor copy)

**Caveat: the CodeIgniter Context7 library serves the LATEST docs, not the
pinned version above.** Fine while latest matches the pin (true as of
2026-07-06). Before trusting a Context7 answer for anything version-sensitive,
cross-check against the pins in this file. If the repo lags a future major,
prefer the pinned canonical URL over Context7.
