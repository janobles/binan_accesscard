---
name: testing-ci
description: Use when writing or debugging tests in this repo, when a CI job goes red, or when a test passes locally but fails in CI. Covers the suite, the two-backend CI setup and why it exists, the rule against building schema in tests, and how to reproduce the MariaDB job locally.
---

# Testing and CI

```bash
vendor/bin/phpunit      # the suite
composer test           # the same thing
```

`docs/22-testing.md` covers what the suite guards. This skill is the operational
side: the two backends, and what to do when one of them is red.

## Never build schema in a test

The dump is the source of truth, so tests import it. `forge()` is not how you get
tables.

`tests/_support/Database/DumpSchema.php` resolves the dump by picking the
highest-numbered `accesscardV*.sql` at test time, so a new dump version needs no
edit here.

A test that builds its own schema is testing against a schema that does not exist
in production, and it passes while doing so. That is the failure mode worth
preventing.

## CI runs the suite twice, and that is the point

`.github/workflows/ci.yml` has two jobs.

**`lint-and-test`** on PHP 8.2 with `sqlite3`: `composer lint:sniff`,
`composer lint:comments`, PHPUnit, `php spark routes`,
`php scripts/check-route-handlers.php`, a Node smoke test, and ESLint over
`public/assets/js`.

**`mariadb-tests`** runs the same PHPUnit suite against MariaDB 10.4, importing
the dump first.

**SQLite does not enforce foreign keys the way MySQL does.** A test leaving an
orphaned row, or inserting in an order that violates a constraint, passes on the
SQLite path and fails against the real database. If `lint-and-test` is green and
`mariadb-tests` is red, that asymmetry is almost always why, and the test is
usually right to fail.

## Reproducing the MariaDB job locally

Do this before pushing anything that touches a write path.

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS accesscard_ci"
mysql -uroot accesscard_ci < "$(ls accesscardV*.sql | sort -V | tail -1)"

database.tests.hostname=127.0.0.1 \
database.tests.database=accesscard_ci \
database.tests.username=root \
database.tests.DBPrefix= \
vendor/bin/phpunit
```

The dotted environment variables are read by CodeIgniter over the `$tests` group
in `app/Config/Database.php`. `DBPrefix` is empty because the dump creates
unprefixed tables, while the SQLite group keeps its `db_` prefix.

Use a scratch database. The suite writes.

## Skipped tests are not failures

`tests/database/` and `tests/session/` need the `sqlite3` extension and skip
without it. A local run reporting skipped tests is normal.

## Lint is part of CI

`composer lint` runs on every pull request to `main`, so a red lint blocks the
merge rather than merely the review. Run it before opening one.

`composer lint:format` and `lint:fix` are **not** in `composer lint` and **not**
in CI. Do not run `lint:fix` across the repository: the repo is deliberately
unformatted, and a whole-repo reformat produces an unreviewable diff while moving
executable tokens.

For a branch that claims to touch only comments or docs, prove it:

```bash
php scripts/assert-tokens-unchanged.php <base-ref>
bash scripts/assert-css-unchanged.sh <base-ref>
```

Both compare against a git ref, fail on added or deleted files, and compare
inline HTML verbatim, so a markup edit in a view cannot slip through.

## Tests that guard structure

These fail loudly by design when you move something, and updating them is part of
the change rather than a nuisance:

- `RouteSpaceTest`: every page resolves at its flat URI, no route carries a role
  prefix, guarded pages declare their manifest key.
- `NavigationManifestTest`: the manifest's shape and role filtering, and that
  `RoleNavFilter` denies a role with no entry.
- `DashboardControllerRoutingTest`: the page-action methods exist. Add a page,
  update this test.
