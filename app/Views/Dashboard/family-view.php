<?php
helper('dashboard_view');
extract(family_details_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel mb-3">
    <div class="section-title mt-0"><span>Family Details</span></div>

    <div class="member-row mb-3 p-3">
        <h6 class="mb-3">Head of Family</h6>
        <div class="row g-2">
            <div class="col-md-4"><small><strong>Name:</strong> <?= esc(trim((string) (($head['firstname'] ?? '') . ' ' . ($head['middlename'] ?? '') . ' ' . ($head['lastname'] ?? '') . ' ' . ($head['suffix'] ?? '')))) ?></small></div>
            <div class="col-md-4"><small><strong>Birthday:</strong> <?= esc((string) ($head['birthday'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Sex:</strong> <?= esc((string) ($head['sex'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Civil status:</strong> <?= esc((string) ($head['civilstatus'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Contact:</strong> <?= esc((string) ($head['contactnumber'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Education:</strong> <?= esc((string) ($head['education'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Job:</strong> <?= esc((string) ($head['job'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Monthly income:</strong> <?= esc((string) ($head['Salary'] ?? '-')) ?></small></div>
            <div class="col-md-4"><small><strong>Sector:</strong> <?= esc((string) ($head['sector_name'] ?? '-')) ?></small></div>
            <div class="col-12">
                <small><strong>Services availed:</strong></small>
                <ul class="mb-0">
                    <?php foreach (($serviceMap[(int) ($head['memberID'] ?? 0)] ?? []) as $serviceId): ?>
                        <li><?= esc((string) ($serviceNameMap[(int) $serviceId] ?? ('Service #' . (int) $serviceId))) ?></li>
                    <?php endforeach; ?>
                    <?php if (($serviceMap[(int) ($head['memberID'] ?? 0)] ?? []) === []): ?>
                        <li class="text-muted">No services availed.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="member-row p-3">
        <h6 class="mb-3">Family Members</h6>
        <?php foreach ($members as $member): ?>
            <?php $memberId = (int) ($member['memberID'] ?? 0); ?>
            <div class="border rounded p-2 mb-2 bg-light">
                <div class="row g-2">
                    <div class="col-md-4"><small><strong>Name:</strong> <?= esc(trim((string) (($member['firstname'] ?? '') . ' ' . ($member['middlename'] ?? '') . ' ' . ($member['lastname'] ?? '') . ' ' . ($member['suffix'] ?? '')))) ?></small></div>
                    <div class="col-md-4"><small><strong>Relationship:</strong> <?= esc((string) ($member['relationship'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Sector:</strong> <?= esc((string) ($member['sector_name'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Birthday:</strong> <?= esc((string) ($member['birthday'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Sex:</strong> <?= esc((string) ($member['sex'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Civil status:</strong> <?= esc((string) ($member['civilstatus'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Education:</strong> <?= esc((string) ($member['education'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Job:</strong> <?= esc((string) ($member['job'] ?? '-')) ?></small></div>
                    <div class="col-md-4"><small><strong>Contact:</strong> <?= esc((string) ($member['contactnumber'] ?? '-')) ?></small></div>
                    <div class="col-12">
                        <small><strong>Services availed:</strong></small>
                        <ul class="mb-0">
                            <?php foreach (($serviceMap[$memberId] ?? []) as $serviceId): ?>
                                <li><?= esc((string) ($serviceNameMap[(int) $serviceId] ?? ('Service #' . (int) $serviceId))) ?></li>
                            <?php endforeach; ?>
                            <?php if (($serviceMap[$memberId] ?? []) === []): ?>
                                <li class="text-muted">No services availed.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($members === []): ?>
            <p class="text-muted mb-0">No family members found.</p>
        <?php endif; ?>
    </div>
</div>
