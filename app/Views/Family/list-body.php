<?php
/**
 * Family records list body: the AJAX DataTable only. The search/filter toolbar
 * moved to components/records_toolbar (rendered above this card by
 * Family/list.php). Variable contract: routeBase.
 */
?>
<div class="table-responsive flex-grow-1 overflow-auto">
    <table
        class="table table-hover align-middle w-100"
        id="familyRecordsTable"
        data-ajax-url="<?= esc(site_url($routeBase . '/data'), 'attr') ?>"
    >
        <thead class="table-light">
        <tr>
            <th class="fw-semibold small text-center">QR NO.</th>
            <th class="fw-semibold small">HEAD/MEMBER NAME</th>
            <th class="fw-semibold small">SECTOR</th>
            <th class="fw-semibold small">ADDRESS</th>
            <th class="fw-semibold small">BIRTHDAY</th>
            <th class="fw-semibold small text-end">ACTIONS</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
