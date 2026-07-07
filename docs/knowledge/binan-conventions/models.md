# Models

**Scope:** model responsibilities, feature grouping, query placement, schema
truth.

## Rule 1: Models mirror the feature subnamespaces

`app/Models/` groups by feature, matching the controllers
(`docs/knowledge/binan-conventions/routing-subnamespaces.md`):

- `Auth/UserModel` — login, password hashing, account creation.
- `Families/` — `MemberModel`, `MemberServiceModel`, `FamilyFormOptionsModel`.
- `Audit/AuditTrailsModel` — audit inserts + list queries
  (`docs/knowledge/binan-conventions/audit-trail.md`).
- `Lookups/` — `SectorModel`, `ServiceModel`, `CategoryModel`.
- `Scanner/`, `Jobs/` — QR/scan and queued-job models.
- Shared cross-feature queries: `DashboardModel`, `SearchModel`,
  `ViewLayoutModel` (top level).
- Reusable query behavior lives in `app/Models/Concerns/` traits
  (`MemberQueryFilters`, `NormalizesIds`, `RecordStatus`,
  `ResolvesMemberNames`, ...), mixed into models — not copy-pasted.

## Rule 2: Queries live in models; controllers and libraries only call them

Controllers/libraries never touch the query builder. Cross-references:
mvc-boundaries.md Rule 3 (this is the same boundary from the model side).

## Rule 3: Canonical model shape

Canonical — `app/Models/Families/MemberModel.php:15`:

```php
class MemberModel extends Model
{
    use MemberQueryFilters;
    use NormalizesIds;
    use ResolvesSectorNames;

    public const VALIDATION_RULES = [
        'sectorID' => 'permit_empty|valid_sector_array',
        'firstname' => 'required|max_length[100]',
        // ...
    ];

    protected $table = 'member';
    protected $primaryKey = 'memberID';
    protected $returnType = 'array';
    protected $allowedFields = [ /* exact dump column names */ ];
```

Pattern notes:
- `$returnType = 'array'` throughout — no entity classes.
- Validation rules as a `public const` so controllers/tests can reference the
  same source (`app/Models/Families/MemberModel.php:21`).
- Model hooks for storage normalization, e.g. `beforeInsert`
  (`app/Models/Families/MemberModel.php:63`).

## Rule 4: Schema truth is the SQL dump — non-negotiable

- **No migrations, ever.** Schema source of truth is the SQL dump
  (`accesscardV3.0.sql` per CLAUDE.md; local dev imports the current
  `accesscardV*.sql`). Never alter schema in code.
- Column names, allowed enum values, and role names match the dump exactly
  (e.g. `sex` is `in_list[Male,Female]` —
  `app/Models/Families/MemberModel.php:29`).
- Employee accounts store as the `User` role.
- Seeds (`app/Database/Seeds/`) add test login accounts ONLY — never
  tables/columns.

**Why:** the DB is owned by the CSWD deployment; code follows the dump, not
the other way around.
