# PHP Idioms (as used in this repo)

**Scope:** PHP 8.2+ language practice as actually written here. Deep language
questions → PHP manual / Context7, cross-check the pins in
`docs/knowledge/sources.md`.

## Rule 1: File header — namespace + imports + docblock, NO strict_types declare

Canonical — `app/Libraries/RoleAccess.php:1`:

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

**Reality check:** zero files under `app/` use `declare(strict_types=1)` —
despite CLAUDE.md's "strict-type conventions" phrasing, strictness here means
*typed signatures* (Rule 2), not the declare. Do not add the declare to a
single file in passing (inconsistent + can change coercion behavior); the
adopt-or-reword decision is tracked as the 🔵 item in
`docs/knowledge/violations.md`.

## Rule 2: Fully typed signatures — params, returns, nullables

Canonical — `app/Libraries/SessionAuditLogger.php:19`:

```php
public static function logLogin(array $user, string $role, ?RequestInterface $request = null): void
```

`?type` nullables, `: void` returns, `mixed` only where input is genuinely
untyped (`app/Libraries/SessionAuditLogger.php:137`).

## Rule 3: Constructor promotion for simple dependencies

Canonical — `app/Libraries/DashboardPageBuilder.php:29`:

```php
public function __construct(private IncomingRequest $request) {}
```

Multi-dependency writers list promoted params one per line
(`app/Libraries/FamilyRecordWriter.php:27`).

## Rule 4: `match` over `switch` for value mapping

Canonical — `app/Libraries/RoleAccess.php:27` (role canonicalization),
`app/Models/ViewLayoutModel.php:16` (page → layout), and the
`match (true)` guard-chain form (`app/Libraries/ViewFormatter.php:240`).

## Rule 5: Shared constants over duplicated literals

Validation rules as `public const` on the model
(`app/Models/Families/MemberModel.php:21`) so controllers and tests reference
one source. DB enum values (e.g. `account_level`) are translated at a single
point (`app/Libraries/RoleAccess.php:16`), never string-compared ad hoc.

## Rule 6: Docblocks explain purpose and constraints, not mechanics

Every class opens with a short purpose docblock
(`app/Libraries/RoleAccess.php:7`); inline comments state constraints the
code can't show (e.g. why Developer audit rows store NULL userID —
`app/Models/Audit/AuditTrailsModel.php:71`).
