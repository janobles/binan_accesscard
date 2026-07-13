# V18 Batches→Service Refactor Implementation Plan (PR 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `aid_type` table with the existing `services` reference data as the thing a distribution batch hands out, split the tabbed `admin/distribution` page into `admin/batches` (with a create modal) and `admin/distributions`, and ship dump V18.

**Architecture:** CodeIgniter 4.7 MVC. Controllers route, `DashboardPageBuilder` assembles view data, models own queries. Schema source of truth is the SQL dump (never migrations). Batch binds `service_id` at open; every scan stamps it into `aid_distribution`.

**Tech Stack:** PHP 8.2+, CI4 4.7.3, Bootstrap 5.3.3, MySQL (`accesscard` @ 127.0.0.1:3306, user root), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-13-batches-service-refactor-design.md`

## Global Constraints

- No migrations; schema lives in the dump. Seeds add login accounts only.
- Every batch/distribution mutation writes `audit_trails` via `Audit/AuditTrailsModel` (`logAction`).
- Typed signatures; NO `declare(strict_types=1)`.
- Column names must match the dump exactly (`service_id`, `serviceID` on `services`).
- Scanner-module models keep the no-DB test posture: safe empty shapes on any Throwable.
- `services.category` holds the category NAME as text (no FK); join `category` on `category.name = services.category` when the code is needed.
- Button colors only via `btn()` (`app/Helpers/ui_helper.php`); toolbar per `docs/knowledge/binan-conventions/ui-design-system.md`.
- Branch off freshly synced main: `git fetch origin && git checkout -B feat/v18-batch-service origin/main`.
- Run `vendor/bin/phpunit` before starting (record baseline) and after every task.

---

### Task 1: V18 dump + patch script

**Files:**
- Create: `accesscardV18.sql` (repo root, next to V17)
- Create: `sql/patches/v18-batch-service.sql`
- Delete: `accesscardV16.sql`

**Interfaces:**
- Produces: schema where `distribution_batch.service_id int NOT NULL` (index `idx_db_service`) and `aid_distribution.service_id int NOT NULL` (index `idx_ad_service`); no `aid_type` table; developer login `developer`/`developer123`.

- [ ] **Step 1: Generate the developer password hash**

```bash
php -r "echo password_hash('developer123', PASSWORD_ARGON2ID), PHP_EOL;"
```

Save the output hash; use it verbatim in both SQL files below.

- [ ] **Step 2: Create `accesscardV18.sql`**

Copy `accesscardV17.sql` → `accesscardV18.sql`, then edit V18:

1. Delete the whole `aid_type` section (its `DROP TABLE IF EXISTS`, `CREATE TABLE`, and `INSERT ... VALUES ('Financial'), ('Rice'), ('Grocery');`).
2. In `CREATE TABLE aid_distribution`: rename column `aid_type_id` → `service_id`; rename `KEY idx_ad_type (aid_type_id)` → `KEY idx_ad_service (service_id)`.
3. In `CREATE TABLE distribution_batch`: rename `aid_type_id` → `service_id`; rename `KEY idx_db_aidtype (aid_type_id)` → `KEY idx_db_service (service_id)`.
4. Remove test seed rows:
   - sector row `(11, 'TS', 'Test', ...)`
   - category row `(8, 'TSC', 'Test Categories', ...)`
   - services rows `(47, 'TS1', ...)` and `(48, 'TSC1', ...)`
   (fix trailing commas/semicolons on the preceding rows).
5. Replace the developer user's password hash (row `(12, 'developer', ...)` in the `users` insert) with the Step 1 hash. Keep `account_level` = `'developer'`, `status` = `'Enable'`.
6. Update any header comment naming the dump version to V18.

- [ ] **Step 3: Create `sql/patches/v18-batch-service.sql`**

```sql
-- V17 -> V18 in-place upgrade. Wipes demo batch/scan data (approved),
-- swaps aid_type for services, removes test reference rows, resets the
-- developer login to developer/developer123.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE aid_distribution;
TRUNCATE TABLE distribution_batch;

ALTER TABLE aid_distribution
  DROP INDEX idx_ad_type,
  CHANGE COLUMN aid_type_id service_id INT(11) NOT NULL,
  ADD KEY idx_ad_service (service_id);

ALTER TABLE distribution_batch
  DROP INDEX idx_db_aidtype,
  CHANGE COLUMN aid_type_id service_id INT(11) NOT NULL,
  ADD KEY idx_db_service (service_id);

DROP TABLE IF EXISTS aid_type;

DELETE FROM services WHERE serviceID IN (47, 48);
DELETE FROM category WHERE categoryID = 8;
DELETE FROM sector   WHERE sectorID = 11;

-- Hash from: php -r "echo password_hash('developer123', PASSWORD_ARGON2ID);"
UPDATE users SET password = '<ARGON2ID_HASH_FROM_STEP_1>'
WHERE username = 'developer';

SET FOREIGN_KEY_CHECKS = 1;
```

Replace `<ARGON2ID_HASH_FROM_STEP_1>` with the real hash before committing.

- [ ] **Step 4: Apply the patch to the local DB and verify**

```bash
mysql -h 127.0.0.1 -u root accesscard < sql/patches/v18-batch-service.sql
mysql -h 127.0.0.1 -u root accesscard -e "SHOW TABLES LIKE 'aid_type'; DESCRIBE distribution_batch; DESCRIBE aid_distribution; SELECT sectorID FROM sector WHERE shortcode='TS'; SELECT categoryID FROM category WHERE code='TSC';"
```

Expected: no `aid_type` table; both tables show `service_id`; test-row selects return empty.

- [ ] **Step 5: Verify V18 dump imports clean into a scratch DB**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS accesscard_v18check; CREATE DATABASE accesscard_v18check;"
mysql -h 127.0.0.1 -u root accesscard_v18check < accesscardV18.sql
mysql -h 127.0.0.1 -u root accesscard_v18check -e "DESCRIBE distribution_batch; SELECT username, account_level FROM users WHERE username='developer';"
mysql -h 127.0.0.1 -u root -e "DROP DATABASE accesscard_v18check;"
```

Expected: import with no errors, `service_id` present, developer row present.

- [ ] **Step 6: Delete V16 and commit**

```bash
git rm accesscardV16.sql
git add accesscardV18.sql sql/patches/v18-batch-service.sql
git commit -m "feat(db): dump V18 - batches bind services, aid_type dropped, test rows removed"
```

(V17 is deleted in Task 8, after the smoke test.)

---

### Task 2: Models — batch and distribution speak service_id

**Files:**
- Modify: `app/Models/Scanner/DistributionBatchModel.php`
- Modify: `app/Models/Scanner/AidDistributionModel.php`
- Delete: `app/Models/Scanner/AidTypeModel.php`
- Test: `tests/unit/AidDistributionModelTest.php` (update existing)

**Interfaces:**
- Produces: `DistributionBatchModel::open(string $name, int $serviceId, int $userId): int`; `activeBatch()`/`allBatches()` rows now carry `service_id`, `service_name`, `service_code`, `category_name`, `category_code`; `AidDistributionModel::logAid(array $data)` requires `service_id`; `historyFor()`/`allDistributions()` rows carry `service` (name) and `service_code`.

- [ ] **Step 1: Update the existing model test to the new shape**

In `tests/unit/AidDistributionModelTest.php`, replace every `aid_type_id` key with `service_id` in `logAid()` fixtures and assertions (test currently guards the reject-on-missing-id path; keep that behavior, renamed). Follow the file's existing sqlite-skip pattern.

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit --filter AidDistributionModelTest
```

Expected: FAIL (logAid still requires `aid_type_id`).

- [ ] **Step 3: Rewrite `DistributionBatchModel` service wiring**

- `allowedFields`: `['name', 'service_id', 'closed_at', 'created_by']`.
- Shared select used by `activeBatch()` and `allBatches()`:

```php
$this->select('distribution_batch.*, services.name AS service_name,'
        . ' services.shortcode AS service_code,'
        . ' services.category AS category_name,'
        . ' category.code AS category_code')
    ->join('services', 'services.serviceID = distribution_batch.service_id', 'left')
    ->join('category', 'category.name = services.category', 'left')
```

- `open()` becomes:

```php
/** Opens a batch; refuses when name blank, service missing, or a batch is open. */
public function open(string $name, int $serviceId, int $userId): int
{
    $name = trim($name);
    if ($name === '' || $serviceId <= 0 || $this->activeBatch() !== null) {
        return 0;
    }

    try {
        if ($this->insert([
            'name'       => $name,
            'service_id' => $serviceId,
            'created_by' => $userId > 0 ? $userId : null,
        ]) === false) {
            return 0;
        }

        return (int) $this->getInsertID();
    } catch (\Throwable $e) {
        return 0;
    }
}
```

Keep the try/catch empty-shape posture everywhere.

- [ ] **Step 4: Rewrite `AidDistributionModel` service wiring**

- `allowedFields`: swap `aid_type_id` → `service_id`.
- `logAid()`: guard and insert use `service_id` (same null/positive handling).
- `historyFor()`: select `services.name AS service, services.shortcode AS service_code`, join `services` on `services.serviceID = aid_distribution.service_id` (drop the aid_type join).
- `allDistributions()`: same join swap; expose `service` and `service_code` columns instead of `aid_type`.

- [ ] **Step 5: Delete `AidTypeModel` and hunt stragglers**

```bash
git rm app/Models/Scanner/AidTypeModel.php
grep -rn "AidTypeModel\|aid_type" app tests --include="*.php"
```

Remaining hits should only be in files owned by Tasks 3–6 (ScanController, DashboardPageBuilder, DistributionController, AidStatsModel, views, other tests). Note them; do not fix here.

- [ ] **Step 6: Run model tests, commit**

```bash
vendor/bin/phpunit --filter AidDistributionModelTest
git add -A && git commit -m "feat(models): batches and distributions bind service_id, drop AidTypeModel"
```

---

### Task 3: Stats + reports pipeline — byAidType → byService

**Files:**
- Modify: `app/Models/Scanner/AidStatsModel.php:110-129`
- Modify: `app/Controllers/Admin/ReportsController.php:66`
- Modify: `app/Libraries/Scanner/ReportsPdfGenerator.php`
- Modify: `app/Views/Scanner/pdf/report.php`
- Modify: `app/Views/Admin/reports-body.php`
- Test: `tests/unit/ReportsPdfGeneratorTest.php` (update existing)

**Interfaces:**
- Consumes: `aid_distribution.service_id` (Task 2 schema).
- Produces: `AidStatsModel::byService(?int $batchId = null): array` returning `list<array{service:string, service_code:string, count:int}>`; `ReportsPdfGenerator::generate(..., array $byService, ...)`.

- [ ] **Step 1: Update `ReportsPdfGeneratorTest` fixtures**

Rename `aid_type` fixture keys to `service` and add `service_code` (e.g. `['service' => 'Relief Food Pack', 'service_code' => 'EDA8', 'count' => 3]`). Assert the rendered PDF/HTML contains `EDA8`.

- [ ] **Step 2: Run to verify failure**

```bash
vendor/bin/phpunit --filter ReportsPdfGeneratorTest
```

Expected: FAIL.

- [ ] **Step 3: Rewrite `AidStatsModel::byAidType()` as `byService()`**

```php
/** Handout counts per service, within the batch scope, busiest first. */
public function byService(?int $batchId = null): array
{
    try {
        $b = $this->db->table('services')
            ->select('services.name AS service, services.shortcode AS service_code,'
                . ' COUNT(aid_distribution.aidID) AS count')
            ->join('aid_distribution', 'aid_distribution.service_id = services.serviceID', 'left');
        $this->applyScope($b, $batchId);
        $rows = $b->groupBy('services.serviceID')
            ->having('count >', 0)
            ->orderBy('count', 'DESC')
            ->orderBy('services.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn ($r) => [
            'service'      => (string) $r['service'],
            'service_code' => (string) ($r['service_code'] ?? ''),
            'count'        => (int) $r['count'],
        ], $rows);
    } catch (\Throwable $e) {
        return [];
    }
}
```

(`HAVING count > 0` because services has ~46 rows; the old 3-row aid_type table listed zeros harmlessly, 46 zero rows is noise.)

- [ ] **Step 4: Rename through the pipeline**

- `ReportsController.php:66`: `'aidType' => $stats->byAidType($scope)` → `'byService' => $stats->byService($scope)` (and mirror the key change where the JSON/stats endpoint or view consumes it — follow the variable to `reports-body.php`).
- `ReportsPdfGenerator::generate()`: param `array $byAidType` → `array $byService`; docblock shape `list<array{service:string,service_code:string,count:int}>`; view data key `'byService'`.
- `pdf/report.php`: loop `$byService`; cell renders `<?= esc($a['service_code'] !== '' ? $a['service_code'] . ' — ' . $a['service'] : $a['service']) ?>`; table heading "Service".
- `reports-body.php`: `$reportsByAidType` → `$reportsByService`; chart id `chartAidType` → `chartService`; chart labels use `service_code ?: service`; heading text "By Service".
- `DashboardPageBuilder::buildReportsData()` (line ~404): rename the corresponding data key it passes (grep `reportsByAidType` and `byAidType` across `app/`).

- [ ] **Step 5: Run tests, commit**

```bash
vendor/bin/phpunit --filter ReportsPdfGeneratorTest && vendor/bin/phpunit
git add -A && git commit -m "feat(reports): stats and PDF report count by service instead of aid type"
```

---

### Task 4: ScanController + kiosk — stamp service, show code

**Files:**
- Modify: `app/Controllers/Scanner/ScanController.php`
- Modify: `app/Views/Scanner/scan.php`
- Modify: `app/Views/Scanner/kiosk-layout.php`
- Test: `tests/unit/ScanControllerBatchTest.php`, `tests/unit/ScanViewTest.php` (update existing)

**Interfaces:**
- Consumes: `activeBatch()` row keys `service_id`, `service_name`, `service_code`, `category_code` (Task 2); `logAid()` requiring `service_id` (Task 2).
- Produces: `scanner/log` JSON `batch` object gains `service` and `service_code` keys (replacing `aid_type`); 409-no-batch behavior unchanged.

- [ ] **Step 1: Update the two scanner tests**

In `ScanControllerBatchTest` and `ScanViewTest`, swap `aid_type_id`/`aid_type_name`/`aidType` fixtures for `service_id`/`service_name`/`service_code` and assert the kiosk badge/JSON carry the service name. Keep the 409-when-no-batch assertions untouched.

- [ ] **Step 2: Run to verify failure**

```bash
vendor/bin/phpunit --filter "ScanControllerBatchTest|ScanViewTest"
```

Expected: FAIL.

- [ ] **Step 3: Update `ScanController`**

- Line ~52: view data `'aidType' => [...]` becomes

```php
'service' => $activeBatch !== null
    ? [
        'service_id' => (int) $activeBatch['service_id'],
        'name'       => (string) ($activeBatch['service_name'] ?? 'Service'),
        'code'       => (string) ($activeBatch['service_code'] ?? ''),
    ]
    : null,
```

- Line ~155 JSON: `'aid_type' => ...aid_type_name...` becomes `'service' => (string) ($activeBatch['service_name'] ?? ''), 'service_code' => (string) ($activeBatch['service_code'] ?? '')`.
- Lines ~229–244: `$aidTypeId = (int) $activeBatch['aid_type_id']` → `$serviceId = (int) $activeBatch['service_id']`; `logAid([... 'service_id' => $serviceId ...])`. Update the audit-detail string to say `service ID`.

- [ ] **Step 4: Update kiosk views**

In `kiosk-layout.php` / `scan.php`, the header badge that rendered the aid-type name now renders `<code> — <name>` (e.g. `EDA8 — Relief Food Pack`; omit the dash when code empty), with the category code as small muted text when present. No new keystrokes, page must still fit the viewport.

- [ ] **Step 5: Run tests, commit**

```bash
vendor/bin/phpunit
git add -A && git commit -m "feat(scanner): scans stamp the batch's service; kiosk badge shows service code"
```

---

### Task 5: Controller + routes — split into batches / distributions pages

**Files:**
- Modify: `app/Controllers/Admin/DistributionController.php`
- Modify: `app/Config/Routes.php:83-94`
- Modify: `app/Libraries/DashboardPageBuilder.php` (distribution data block ~113–199, `navActive` ~174)
- Modify: `app/Views/components/dashboard_sidebar.php:36`

**Interfaces:**
- Consumes: `DistributionBatchModel::open(name, serviceId, userId)` (Task 2), `ServiceModel`/`CategoryModel` lookups (`app/Models/Lookups/`).
- Produces: GET `admin/batches` → `DistributionController::batches()`; GET `admin/distributions` → `DistributionController::distributions()`; POST `admin/batches/open|close/(:num)` redirect to `admin/batches`; POST `admin/distributions/void/(:num)` redirects to `admin/distributions`. PageBuilder page keys `'batches'` and `'distributions'`; batches page data: `batches`, `activeBatch`, `activeCategories` (active category rows), `activeServices` (active service rows incl. `serviceID`, `shortcode`, `category`, `name`); distributions page data: `distributions`.

- [ ] **Step 1: Rewrite `DistributionController`**

Delete `createAidType/archiveAidType/restoreAidType/deleteAidType` and the `AidTypeModel` import. Replace `index()` with two page actions; retarget redirects:

```php
/** GET admin/batches — batch control page (create modal, open/close). */
public function batches(): ResponseInterface|string
{
    if ($g = $this->guard()) { return $g; }

    return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('batches');
}

/** GET admin/distributions — every handout, searchable log. */
public function distributions(): ResponseInterface|string
{
    if ($g = $this->guard()) { return $g; }

    return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('distributions');
}
```

`openBatch()`: read `service_id` post field, validate `> 0` with error "Choose a service for this batch.", call `open($name, $serviceId, ...)`, audit `'Opened distribution batch "' . $name . '" #' . $id . ' (service ID ' . $serviceId . ')'`, all redirects → `admin/batches`. `closeBatch()` redirects → `admin/batches`. `voidDistribution()` redirects → `admin/distributions` and its audit detail says `service ID` using `$row['service_id']`.

- [ ] **Step 2: Update routes**

Replace `Routes.php:83-94` block with:

```php
$routes->group('batches', static function (RouteCollection $routes): void {
    $routes->get('', 'Admin\DistributionController::batches');
    $routes->post('open', 'Admin\DistributionController::openBatch');
    $routes->post('close/(:num)', 'Admin\DistributionController::closeBatch/$1');
});
$routes->group('distributions', static function (RouteCollection $routes): void {
    $routes->get('', 'Admin\DistributionController::distributions');
    $routes->post('void/(:num)', 'Admin\DistributionController::voidDistribution/$1');
});
```

(no `admin/distribution` route, no `aid-types` group).

- [ ] **Step 3: Update `DashboardPageBuilder`**

- Replace the `$isDistribution` block: `$isBatches = $activePage === 'batches'; $isDistributions = $activePage === 'distributions';`.
- Drop `AidTypeModel` usage entirely.
- Data keys:

```php
'batches'          => $isBatches ? $batchModel->allBatches() : [],
'activeBatch'      => $isBatches ? $batchModel->activeBatch() : null,
'activeCategories' => $isBatches ? model(\App\Models\Lookups\CategoryModel::class)->getActive() : [],
'activeServices'   => $isBatches ? model(\App\Models\Lookups\ServiceModel::class)->getActive() : [],
'distributions'    => $isDistributions ? model(AidDistributionModel::class)->allDistributions() : [],
```

(If `ServiceModel` has no `getActive()`, use its existing active/not-deleted listing method — check `app/Models/Lookups/ServiceModel.php` and reuse, don't invent.)
- `navActive`: replace the `'distribution'` entry with `'batches'` and `'distributions'` keys.
- Page title/heading map: add entries for both pages ("Distribution Batches", "All Distributions") wherever `'distribution'` was mapped.

- [ ] **Step 4: Update sidebar**

`dashboard_sidebar.php:36` — replace the single Distribution link with:

```php
<a class="nav-link <?= esc($navActive['batches'] ?? '') ?>" href="<?= site_url('admin/batches') ?>"><div class="sb-nav-link-icon"><i class="bi bi-collection" aria-hidden="true"></i></div>Batches</a>
<a class="nav-link <?= esc($navActive['distributions'] ?? '') ?>" href="<?= site_url('admin/distributions') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></div>Distributions</a>
```

- [ ] **Step 5: Verify routes resolve, commit**

```bash
php spark routes | grep -E "batches|distributions"
vendor/bin/phpunit
git add -A && git commit -m "feat(admin): split distribution hub into batches and distributions pages"
```

Expected: `admin/batches` GET/POSTs and `admin/distributions` GET/POST listed; no `admin/distribution` or `aid-types` routes; tests green.

---

### Task 6: Views — batches page with create modal, distributions page

**Files:**
- Modify: `app/Views/Admin/layout.php` (replace the whole `$activePage === 'distribution'` block, ~lines 247–330+)
- Modify: `app/Views/Admin/distribution-batches-body.php`
- Modify: `app/Views/Admin/distribution-distributions-body.php`
- Create: `app/Views/Admin/batch-create-modal.php`
- Delete: `app/Views/Admin/distribution-aidtypes-body.php`

**Interfaces:**
- Consumes: page data from Task 5 (`batches`, `activeBatch`, `activeCategories`, `activeServices`, `distributions`); `btn()` helper; `components/card` view.
- Produces: `admin/batches` and `admin/distributions` render as single-purpose pages, no tabs.

- [ ] **Step 1: Replace the layout block**

Remove the `'distribution'` tab markup, the `#addAidTypeModal`, and the aid-type filter wiring in the inline script. Add two blocks:

```php
<?php if ($activePage === 'batches'): ?>
    <?= view('components/card', [
        'icon' => 'collection',
        'title' => 'Distribution Batches',
        'cardClass' => 'sector-management',
        'bodyView' => 'Admin/distribution-batches-body',
        'bodyData' => [
            'batches' => $batches,
            'activeBatch' => $activeBatch,
            'currentRole' => $currentRole,
        ],
    ]) ?>
    <?= view('Admin/batch-create-modal', [
        'activeCategories' => $activeCategories,
        'activeServices' => $activeServices,
    ]) ?>
<?php endif; ?>

<?php if ($activePage === 'distributions'): ?>
    <?= view('components/card', [
        'icon' => 'clipboard-check-fill',
        'title' => 'All Distributions',
        'cardClass' => 'sector-management',
        'bodyView' => 'Admin/distribution-distributions-body',
        'bodyData' => ['distributions' => $distributions],
        'footer' => '<span id="distCount"></span>',
    ]) ?>
<?php endif; ?>
```

Keep the existing distributions search/paging script but scope it to `$activePage === 'distributions'` and delete the `distAidFilter` control references (the aid-type filter select dies; a service filter returns in PR 2 if wanted).

- [ ] **Step 2: Create `batch-create-modal.php`**

```php
<?php
/**
 * New Batch modal: name + category -> service pick. Services are embedded
 * as JSON and the service select is filtered client-side by the chosen
 * category (services.category stores the category NAME).
 *
 * Variables: $activeCategories (list of category rows), $activeServices
 * (list of service rows: serviceID, shortcode, category, name).
 */
$activeCategories = $activeCategories ?? [];
$activeServices   = $activeServices ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/batches/open') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="newBatchModalLabel">New Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="batchName" class="form-label">Batch name</label>
          <input type="text" class="form-control" id="batchName" name="name" required maxlength="100" placeholder="e.g. July 2026 Relief — Brgy. Canlalay">
        </div>
        <div class="mb-3">
          <label for="batchCategory" class="form-label">Category</label>
          <select class="form-select" id="batchCategory" required>
            <option value="" selected disabled>Choose a category...</option>
            <?php foreach ($activeCategories as $c): ?>
              <option value="<?= esc($c['name'], 'attr') ?>"><?= esc($c['code']) ?> — <?= esc($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="batchService" class="form-label">Service / program</label>
          <select class="form-select" id="batchService" name="service_id" required disabled>
            <option value="" selected disabled>Choose a category first...</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="<?= btn('cancel') ?>" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Open Batch</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const services = <?= json_encode(array_map(static fn (array $s) => [
      'id'       => (int) $s['serviceID'],
      'code'     => (string) ($s['shortcode'] ?? ''),
      'name'     => (string) ($s['name'] ?? ''),
      'category' => (string) ($s['category'] ?? ''),
  ], $activeServices)) ?>;
  const catSel = document.getElementById('batchCategory');
  const svcSel = document.getElementById('batchService');
  catSel.addEventListener('change', () => {
    svcSel.innerHTML = '<option value="" selected disabled>Choose a service...</option>';
    services.filter(s => s.category === catSel.value).forEach(s => {
      const o = document.createElement('option');
      o.value = s.id;
      o.textContent = s.code ? s.code + ' — ' + s.name : s.name;
      svcSel.appendChild(o);
    });
    svcSel.disabled = false;
  });
});
</script>
```

Check `btn()` roles in `app/Helpers/ui_helper.php` first and use its real role names (per ui-design-system: add = `#198754` role; use whatever role string maps to it, e.g. `btn('add')` / `btn('cancel')` — do NOT hardcode classes).

- [ ] **Step 3: Rework `distribution-batches-body.php`**

- Delete the old inline open-batch `<form>` (name + aid-type select); replace with a "New Batch" button: `<button type="button" class="<?= btn('add') ?>" data-bs-toggle="modal" data-bs-target="#newBatchModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Batch</button>`.
- Active-batch panel: show `service_code — service_name` and `category_code` badge instead of aid-type name; keep the Close Batch button/POST unchanged (action `admin/batches/close/<id>`).
- Batch table columns: Batch, Service (`service_code — service_name`), Category (`category_code`), Opened, Closed, Status. Rows come from `$batches` (Task 2 keys).

- [ ] **Step 4: Rework `distribution-distributions-body.php`**

Replace the `aid_type` column with Service rendering `<?= esc($d['service_code'] !== '' ? $d['service_code'] . ' — ' . $d['service'] : $d['service']) ?>`; remove the aid-type filter `<select id="distAidFilter">`. Keep search, per-page, void button (action now `admin/distributions/void/<id>`).

- [ ] **Step 5: Delete the aid-types body**

```bash
git rm app/Views/Admin/distribution-aidtypes-body.php
grep -rn "aidtypes\|aidTypes\|aid_type" app/Views app/Libraries app/Controllers --include="*.php"
```

Expected: zero hits (anything left is a missed edit — fix it).

- [ ] **Step 6: Manual smoke via dev server, run suite, commit**

```bash
vendor/bin/phpunit && php spark routes > /dev/null && echo ROUTES-OK
```

Start server if down, then click through: login developer/developer123 → `admin/batches` → New Batch modal (category filters services) → open → kiosk `scanner/scan` shows service badge → scan logs → close batch → `admin/distributions` shows the row with service code.

```bash
git add -A && git commit -m "feat(admin): batches page with category->service create modal; distributions page"
```

---

### Task 7: Docs + knowledge base

**Files:**
- Modify: `docs/knowledge/binan-conventions/scanner-batches.md`
- Modify: `CLAUDE.md` (dump reference line)
- Modify: `PROJECT_STRUCTURE.md` (if it names deleted/added files)

**Interfaces:** none (docs).

- [ ] **Step 1: Rewrite `scanner-batches.md`**

Update Rules 1–5: batch binds `service_id` (services/category reference data, V18); aid-type CRUD is gone; pages are `admin/batches` + `admin/distributions` (no tabs); kiosk badge shows service shortcode + name; stats method is `byService()`. Keep the developer-account caveat but note the login is now a real `users` row (`developer`/`developer123`).

- [ ] **Step 2: Update CLAUDE.md dump line**

`accesscardV3.0.sql` reference → `accesscardV18.sql`.

- [ ] **Step 3: Validate cites, commit**

```bash
bash scripts/check-knowledge-cites.sh
git add -A && git commit -m "docs: scanner-batches knowledge + dump reference updated for V18"
```

Expected: cite check passes.

---

### Task 8: Full verification, V17 removal, PR

**Files:**
- Delete: `accesscardV17.sql`

- [ ] **Step 1: Full suite + routes**

```bash
vendor/bin/phpunit
php spark routes | grep -cE "admin/(batches|distributions)"
```

Expected: suite green; grep count ≥ 5.

- [ ] **Step 2: End-to-end smoke on a FRESH V18 import**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE accesscard; CREATE DATABASE accesscard;"
mysql -h 127.0.0.1 -u root accesscard < accesscardV18.sql
```

Then repeat the Task 6 click-through (login, modal open batch, scan, close, distributions log, reports page renders with By Service, PDF export downloads). This is the gate for deleting V17.

- [ ] **Step 3: Delete V17, commit**

```bash
git rm accesscardV17.sql
git commit -m "chore(db): drop V17 dump; V18 verified end-to-end"
```

- [ ] **Step 4: Code review**

```bash
coderabbit auth status && coderabbit review --base main --agent
```

If the CLI refuses (trial ended), fall back to `/code-review` on the branch. Triage per `superpowers:receiving-code-review` — verify each finding, fix in-scope bugs, park the rest in a GitHub issue citing the PR.

- [ ] **Step 5: PR**

```bash
git push -u origin feat/v18-batch-service
gh pr create --repo janobles/binan_accesscard --base main \
  --title "feat: V18 — batches bind services/programs; distribution hub split" \
  --body "$(cat <<'EOF'
Replaces the aid_type table with the existing services/programs reference data (spec: docs/superpowers/specs/2026-07-13-batches-service-refactor-design.md).

- Dump V18 + in-place patch sql/patches/v18-batch-service.sql (V16/V17 removed)
- distribution_batch/aid_distribution now stamp service_id; kiosk badge and reports show service shortcodes
- admin/distribution tabs split into admin/batches (create modal, category -> service) and admin/distributions
- Test reference rows (TS/TSC/TS1/TSC1) removed; developer/developer123 restored

PR 2 (QR pages UI/UX pass) follows.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Deferred to PR 2 (separate plan, written after this PR merges)

UI/UX pass on the QR Code group per the spec: Manage Records toolbar standard on Batches/Distributions/Generate/Reports, kiosk polish, Playwright desktop+390px verification.
