# Audit trails

Someone will eventually ask why a family's record says one thing when the form
they signed said another. The audit trail is the answer to that question, and it
is the reason this is a non-negotiable rather than a nice-to-have.

It is not debug logging. It is the product's accountability surface, and the CSWD
requires a per-mutation trail.

## What a row holds

`audit_trails` records the acting user, the affected member, the action, a short
description, a longer narrative, the IP address, the user agent, and the
timestamp.

Two of the columns are nullable for reasons worth knowing.

`memberID` is null when the change affected no particular person. A change to the
sector list is a change to the system, not to someone's record.

`userID` is null when the actor has no `users` row, which `logAction()` writes as
NULL for any user id of 0 (`app/Models/Audit/AuditTrailsModel.php:78`). That covers
failed logins and system errors, and it covered the file-backed developer account
this app used before the account moved into the database. Storing NULL keeps the
foreign key intact and has the useful side effect that those rows stay hidden from
non-developer viewers. The dump's `developer` account has a real row, so log in
with that and your actions carry your user id.

## The rule

**Every family mutation writes an `audit_trails` row through
`App\Models\Audit\AuditTrailsModel`.** Creating, updating, archiving, and
restoring all do it.

The consequence of skipping it is not that a log line goes missing. It is that the
change becomes indistinguishable, afterwards, from data that was never changed.
There is no second source to reconstruct it from. That is why the anti-pattern
below is stated as strongly as it is.

**Never insert into `audit_trails` directly with the query builder, and never skip
the audit on an "internal" mutation path.** An internal path is exactly the one
nobody will remember exists when the question is asked.

Silent write failures have to surface on the audit page rather than in a log file,
which is what the `auditSystemError` fallback in
`app/Controllers/Families/FamilyRequestContext.php` is for. That trait is shared
by the three Families controllers.

## How it is called

```php
public function logAction(
    int $userId,
    ?int $memberId,
    string $action,
    ?string $description = null,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?string $detail = null
): bool
```

Action names are SCREAMING_SNAKE domain events: `FAMILY_CREATED`,
`FAMILY_UPDATED`, and the archive and restore variants. They name what happened
in the domain, not which HTTP verb arrived.

Pass the IP address and user agent from the request; `logAction()` composes the
full narrative itself rather than expecting the caller to format one.

## Why multi-table writes bundle the audit

When a mutation spans the member row, the service assignments, and the audit, the
sequence lives in `app/Libraries/FamilyRecordWriter.php`, which is constructed
with the audit model.

That is not tidiness. It means the audit row cannot be forgotten by a future
caller, because there is no way to use the writer that omits it. Both the entry
form and the import worker go through it, so both are audited by construction
rather than by discipline.

New multi-table family writes should go through this writer, or mirror its shape.

## Session events go somewhere else

Login, logout, and failed login are session-level events, not record mutations,
and they have their own helper:

```php
SessionAuditLogger::logFailedLogin($username, 'invalid username or password', $this->request);
```

alongside `logLogin()` and `logLogoutFromSession()`. Use `SessionAuditLogger` for
authentication and session events, and `AuditTrailsModel::logAction()` for record
mutations.

## System-written rows

Not every row has a person behind it. When a distribution batch opens or closes on
its own schedule, that transition writes its own audit row under user id 0, which
reads as "system" on the audit page. Chapter 15 covers why batches transition
without anyone clicking anything.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

**Scope:** when and how mutations get audited. Non-negotiable:
**every family mutation writes an `audit_trails` row via
`App\Models\Audit\AuditTrailsModel` - never bypass it.**

### Rule 1: Every family mutation logs via `AuditTrailsModel::logAction()`

Signature - `app/Models/Audit/AuditTrailsModel.php:53`:

```php
public function logAction(
    int $userId,
    ?int $memberId,
    string $action,
    ?string $description = null,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?string $detail = null
): bool
```

Canonical call - family update, `app/Controllers/Families/FamilyController.php:418`:

```php
$auditModel->logAction(
    $userId,
    $headId,
    'FAMILY_UPDATED',
    'Updated family profile for ' . $headName . '.',
    $this->request->getIPAddress(),
    $this->request->getUserAgent()->getAgentString(),
    'Head of family: ' . $headName . '; ' . $memberCount . ' member(s) in household; '
        . $serviceCount . ' service(s) on the head after update'
);
```

Pattern notes:
- Guard with `$auditModel->hasTable()` first
  (`app/Controllers/Families/FamilyController.php:413`).
- Action names are SCREAMING_SNAKE domain events: `FAMILY_CREATED`,
  `FAMILY_UPDATED`, archive/restore variants.
- Pass IP + user agent from the request; `logAction()` composes the
  full six-facet narrative itself (`app/Models/Audit/AuditTrailsModel.php:81`).
- The `.env` Developer (userID 0) is stored as NULL userID so the users FK
  holds and its rows stay hidden from non-developer viewers
  (`app/Models/Audit/AuditTrailsModel.php:74`).

**Anti-pattern:** inserting into `audit_trails` directly with the query
builder, or skipping the audit call on an "internal" mutation path. Silent
write failures must surface on the audit page - see the error-audit fallback
`auditSystemError` (`app/Controllers/Families/FamilyRequestContext.php:80`,
a trait shared by the three Families controllers).

**Why:** CSWD requires a per-mutation trail; the audit page is the product's
accountability surface, not debug logging.

### Rule 2: Multi-write sequences bundle the audit into the writer library

When a mutation spans member + services + audit,
`app/Libraries/FamilyRecordWriter.php:1` owns the sequence - constructed with
the audit model (`app/Controllers/Families/FamilyController.php:161`):

```php
$writer = new FamilyRecordWriter($memberModel, $memberServiceModel, $serviceModel, $auditModel);
```

New multi-table family writes go through (or mirror) this writer so the audit
row can't be forgotten.

### Rule 3: Session events use `SessionAuditLogger`, not `logAction()` directly

Login/logout/failed-login are session-level events with their own static
helper - canonical: `app/Controllers/Auth/AuthController.php:57`:

```php
SessionAuditLogger::logFailedLogin($username, 'invalid username or password', $this->request);
```

plus `logLogin()` (`:92`) and `logLogoutFromSession()` (`:106`). Use it for
auth/session events; use `AuditTrailsModel::logAction()` for record
mutations.
