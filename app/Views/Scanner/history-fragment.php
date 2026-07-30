<?php
/**
 * Family Information/Audit Logs tabs - the reusable guts of the "View all" page.
 * Rendered two ways from the same data (Scanner\ScanController::history()):
 * as the body of the full Scanner/history.php page, and as a bare AJAX
 * fragment injected straight into the scan panel (isAJAX() request) so the
 * kiosk can show it inline without leaving scan.php.
 */
$head    = (array) ($head ?? []);
$members = (array) ($members ?? []);

$familyFullName = static fn (array $p): string => trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? ''));

$rows = [
    [
        'relationship'     => 'Head',
        'fullName'         => $familyFullName($head),
        'suffix'           => (string) ($head['suffix'] ?? ''),
        'birthday'         => (string) ($head['birthday'] ?? ''),
        'sex'              => (string) ($head['sex'] ?? ''),
        'civilstatus'      => (string) ($head['civilstatus'] ?? ''),
        'contact'          => (string) ($head['contactnumber'] ?? ''),
        'job'              => (string) ($head['job'] ?? ''),
        'address'          => (string) ($head['address'] ?? ''),
        'sectors'          => (array) ($head['sectors'] ?? []),
        'servicesPrograms' => (array) ($head['servicesPrograms'] ?? []),
    ],
];
foreach ($members as $m) {
    $rows[] = [
        'relationship'     => (string) ($m['relationship'] ?? 'Member'),
        'fullName'         => $familyFullName($m),
        'suffix'           => (string) ($m['suffix'] ?? ''),
        'birthday'         => (string) ($m['birthday'] ?? ''),
        'sex'              => (string) ($m['sex'] ?? ''),
        'civilstatus'      => (string) ($m['civilstatus'] ?? ''),
        'contact'          => (string) ($m['contactnumber'] ?? ''),
        'job'              => (string) ($m['job'] ?? ''),
        'address'          => (string) ($m['address'] ?? ''),
        'sectors'          => (array) ($m['sectors'] ?? []),
        'servicesPrograms' => (array) ($m['servicesPrograms'] ?? []),
    ];
}
?>
<ul class="nav nav-pills segmented-tabs mb-3" id="historyPageTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="familyTabToggle" data-bs-toggle="tab" data-bs-target="#familyTabPane" type="button" role="tab" aria-controls="familyTabPane" aria-selected="true">Family Information</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="historyTabToggle" data-bs-toggle="tab" data-bs-target="#historyTabPane" type="button" role="tab" aria-controls="historyTabPane" aria-selected="false">Audit Logs</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="familyTabPane" role="tabpanel" aria-labelledby="familyTabToggle">
    <?= view('Scanner/family-tab-body', get_defined_vars()) ?>
  </div>

  <div class="tab-pane fade" id="historyTabPane" role="tabpanel" aria-labelledby="historyTabToggle">
    <?= view('Scanner/history-tab-body', get_defined_vars()) ?>
  </div>
</div>
