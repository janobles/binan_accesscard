# Audit Trail

**Scope:** when and how mutations get audited. Non-negotiable (CLAUDE.md):
**every family mutation writes an `audit_trails` row via
`App\Models\Audit\AuditTrailsModel` — never bypass it.**

## Rule 1: Every family mutation logs via `AuditTrailsModel::logAction()`

Signature — `app/Models/Audit/AuditTrailsModel.php:53`:

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

Canonical call — family update, `app/Controllers/Families/FamilyController.php:418`:

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
write failures must surface on the audit page — see the error-audit fallback
`auditSystemError` (`app/Controllers/Families/FamilyRequestContext.php:80`,
a trait shared by the three Families controllers).

**Why:** CSWD requires a per-mutation trail; the audit page is the product's
accountability surface, not debug logging.

## Rule 2: Multi-write sequences bundle the audit into the writer library

When a mutation spans member + services + audit,
`app/Libraries/FamilyRecordWriter.php:1` owns the sequence — constructed with
the audit model (`app/Controllers/Families/FamilyController.php:161`):

```php
$writer = new FamilyRecordWriter($memberModel, $memberServiceModel, $serviceModel, $auditModel);
```

New multi-table family writes go through (or mirror) this writer so the audit
row can't be forgotten.

## Rule 3: Session events use `SessionAuditLogger`, not `logAction()` directly

Login/logout/failed-login are session-level events with their own static
helper — canonical: `app/Controllers/Auth/AuthController.php:57`:

```php
SessionAuditLogger::logFailedLogin($username, 'invalid username or password', $this->request);
```

plus `logLogin()` (`:92`) and `logLogoutFromSession()` (`:106`). Use it for
auth/session events; use `AuditTrailsModel::logAction()` for record
mutations.
