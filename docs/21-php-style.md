# PHP style

PHP 8.2 as actually written in this repository. For deep language questions, go
to the PHP manual; `docs/reference/version-pins.md` records what this repo is
pinned to.

## No `declare(strict_types=1)`

Zero files under `app/` use it, and that is deliberate rather than an oversight.
It matches the CodeIgniter appstarter, which the repository has stayed close to.

Strictness here means **typed signatures**, not the declare.

Do not add the declare to a single file in passing. It would be inconsistent with
every other file, and it can change coercion behaviour in ways that are invisible
until they are not. This was settled once already: the agent instructions used to
say "respect existing strict-type conventions" while no file carried the declare,
and the wording was changed to typed signatures rather than the code being
changed (`docs/reference/violations.md`).

## File shape

Namespace, imports, class docblock, class. No more ceremony than that:

```php
<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Central role/authorization helper used by controllers, the page builder, and
 * filters to gate access and route users to the right dashboard.
 */
class RoleAccess
```

## Typed everything

Parameters, returns, and nullables:

```php
public static function logLogin(array $user, string $role, ?RequestInterface $request = null): void
```

`?type` for nullables, `: void` where nothing comes back, and `mixed` only where
the input genuinely is untyped.

## Constructor promotion

For simple dependencies, promote in the signature:

```php
public function __construct(private IncomingRequest $request) {}
```

Classes taking several dependencies list the promoted parameters one per line,
which reads better than a wrapped single line and diffs better when one changes.

## `match` over `switch`

Value mapping uses `match`. `RoleAccess::normalizeRole()` mapping the
`account_level` enum to a role label is the canonical example, and
`ViewFormatter` shows the `match (true)` guard-chain form for conditions rather
than values.

## Shared constants over duplicated literals

Validation rules live as a `public const` on the model, so controllers and tests
reference one source rather than three copies that drift.

Database enum values are translated at exactly one point. `RoleAccess` is that
point for `account_level`, and nothing string-compares those values ad hoc. When
the dump changes an enum, there is one place to update.

## Docblocks

Every class opens with a short purpose docblock: what it is, who calls it, why it
exists. Methods get one only when it says something the signature cannot.

Inline comments state constraints the code cannot show. Why a Developer's audit
rows store a NULL `userID` is a comment; what the next line does is not.

The full standard, including what the linter enforces, is
`docs/reference/comment-standard.md`.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

**Scope:** PHP 8.2+ language practice as actually written here. Deep language
questions go to the PHP manual, cross-checked against the pins in
`docs/reference/version-pins.md`.

### Rule 1: File header - namespace + imports + docblock, NO strict_types declare

Canonical - `app/Libraries/RoleAccess.php:1`:

```php
<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Central role/authorization helper used by controllers, the page builder, and
 * filters to gate access and route users to the right dashboard.
 */
class RoleAccess
```

**Reality check:** zero files under `app/` use `declare(strict_types=1)`.
Strictness here means *typed signatures* (Rule 2), not the declare. Do not add
the declare to a single file in passing (inconsistent + can change coercion
behavior); the adopt-or-reword decision is tracked as the 🔵 item in
`docs/reference/violations.md`.

### Rule 2: Fully typed signatures - params, returns, nullables

Canonical - `app/Libraries/SessionAuditLogger.php:19`:

```php
public static function logLogin(array $user, string $role, ?RequestInterface $request = null): void
```

`?type` nullables, `: void` returns, `mixed` only where input is genuinely
untyped (`app/Libraries/SessionAuditLogger.php:137`).

### Rule 3: Constructor promotion for simple dependencies

Canonical - `app/Libraries/DashboardPageBuilder.php:29`:

```php
public function __construct(private IncomingRequest $request) {}
```

Multi-dependency writers list promoted params one per line
(`app/Libraries/FamilyRecordWriter.php:27`).

### Rule 4: `match` over `switch` for value mapping

Canonical - `app/Libraries/RoleAccess.php:21` (account_level enum value to role
label), and the `match (true)` guard-chain form
(`app/Libraries/ViewFormatter.php:240`).

### Rule 5: Shared constants over duplicated literals

Validation rules as `public const` on the model
(`app/Models/Families/MemberModel.php:22`) so controllers and tests reference
one source. DB enum values (e.g. `account_level`) are translated at a single
point (`app/Libraries/RoleAccess.php:16`), never string-compared ad hoc.

### Rule 6: Docblocks explain purpose and constraints, not mechanics

Every class opens with a short purpose docblock
(`app/Libraries/RoleAccess.php:7`); inline comments state constraints the
code can't show (e.g. why Developer audit rows store NULL userID -
`app/Models/Audit/AuditTrailsModel.php:71`).
