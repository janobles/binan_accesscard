# Testing

```bash
vendor/bin/phpunit      # the suite
composer test           # the same thing
```

Run it before you change something and after. The suite is fast enough that there
is no reason not to.

## What is in it

`tests/unit/` holds most of it, and the tests are mostly about invariants rather
than about individual functions. A few worth knowing because they will fail on you
if you change the wrong thing:

- `RouteSpaceTest` asserts every page resolves at its flat URI, that no route
  carries a role prefix, and that guarded pages declare their manifest key.
- `NavigationManifestTest` asserts the manifest's shape, its role filtering, and
  that `RoleNavFilter` denies a role with no entry.
- `DashboardControllerRoutingTest` asserts the page-action methods exist. Moving
  or renaming one fails loudly, which is the point. Update it when you add a page.
- `BatchScheduleWindowTest` exercises the open and close arithmetic directly,
  which is possible because that class touches no database.

`tests/database/` and `tests/session/` need the `sqlite3` PHP extension and skip
when it is missing. A local run reporting skipped tests is normal, not a broken
checkout.

`tests/js/entry-page-gate.smoke.mjs` is a Node smoke test for the entry page's
control-number gate, run separately in CI.

## Never build schema in a test

The dump is the source of truth (chapter 02), so tests import it rather than
constructing tables with `forge()`.

`tests/_support/Database/DumpSchema.php` resolves which dump to use by picking the
highest-numbered `accesscardV*.sql` at test time. It is deliberately not a
hardcoded filename: cutting a new dump version should not require editing the test
support or the CI workflow.

A test that builds its own schema with `forge()` is testing against a schema that
does not exist in production. That is worse than no test, because it passes.

## CI runs the suite twice

`.github/workflows/ci.yml` has two jobs, and understanding why saves confusion
when one goes red and the other does not.

**`lint-and-test`** runs on PHP 8.2 with `sqlite3`. It runs `composer lint:sniff`,
`composer lint:comments`, the PHPUnit suite, `php spark routes`,
`php scripts/check-route-handlers.php`, the Node smoke test, and ESLint over
`public/assets/js`.

**`mariadb-tests`** runs the same PHPUnit suite against a real MariaDB 10.4
service, importing the dump first.

The second job exists because **SQLite does not enforce foreign keys the way
MySQL does**. A test that leaves an orphaned row, or an insert whose order
violates a constraint, passes happily on the SQLite path and fails against the
real database. Without the MariaDB job you would find that out in production.

CI resolves the dump the same way the tests do:

```bash
mysql -h 127.0.0.1 -P 3306 -uroot -proot < "$(ls accesscardV*.sql | sort -V | tail -1)"
```

No database argument, because the dump declares `CREATE DATABASE` and `USE`
itself.

The MariaDB job passes connection settings as dotted environment variables that
CodeIgniter reads over the `$tests` group in `app/Config/Database.php`:

```
database.tests.hostname = 127.0.0.1
database.tests.port     = 3306
database.tests.database = accesscard
database.tests.username = root
```

`DBPrefix` is empty there because the dump creates unprefixed tables, while the
SQLite group keeps its `db_` prefix.

## Reproducing the MariaDB job locally

Worth doing before pushing anything that touches a write path, because it is the
job most likely to catch what your local run cannot.

Create a scratch database, import the dump, and run the suite with the same dotted
environment variables:

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS accesscard_ci"
mysql -uroot accesscard_ci < "$(ls accesscardV*.sql | sort -V | tail -1)"

database.tests.hostname=127.0.0.1 \
database.tests.database=accesscard_ci \
database.tests.username=root \
database.tests.DBPrefix= \
vendor/bin/phpunit
```

Use a scratch database, not your development one. The suite writes.

## `composer lint` is part of CI

A red lint blocks the merge, not just the review. Run it before opening a pull
request:

```bash
composer lint            # both layers
composer lint:sniff      # phpcs only
composer lint:comments   # view and CSS headers, banned patterns
```

`composer lint:format` and `lint:fix` exist but are **not** in `composer lint` and
**not** in CI. Do not run `lint:fix` across the repository: the repo is
deliberately unformatted, and a whole-repo reformat produces a diff nobody can
review while moving executable tokens. `docs/reference/comment-standard.md`
explains the reasoning and documents the two scripts that prove a docs-only branch
really is docs-only.

## Smoke tests worth running by hand

Automated tests do not cover everything, and these four flows are where breakage
hurts most:

1. Log in, and confirm the role lands on the right page.
2. Create a family and check it appears in the records list.
3. Update that family and check the change sticks.
4. Open the audit page and confirm both actions were logged.

If all four work, you have not broken anything structural.
