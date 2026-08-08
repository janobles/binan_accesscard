<?php
/**
 * Distribution body (Developer, Admin, Viewer).
 *
 * Rendered inside layout.php as the `distribution` page body. The schedule
 * calendar, the batches list, and the distribution log share one page,
 * switched by ?tab=; data comes from DashboardPageBuilder::buildViewData().
 * Who reaches this page at all is the roleNav filter's decision
 * (Config\Navigation).
 */

$distributionTab = (string) ($distributionTab ?? 'schedule');
$distributionTab = in_array($distributionTab, ['schedule', 'batches', 'log'], true) ? $distributionTab : 'schedule';
?>
<?= view('components/page_tabs', [
    'tabs' => [
        ['key' => 'schedule', 'label' => 'Schedule'],
        ['key' => 'batches', 'label' => 'Batches'],
        ['key' => 'log', 'label' => 'Distribution Log'],
    ],
    'active' => $distributionTab,
    'baseUrl' => 'distribution',
]) ?>

<?php if ($distributionTab === 'schedule'): ?>
    <?= view('Admin/schedule-calendar', ['currentRole' => $currentRole ?? '']) ?>
    <?php if (in_array($currentRole ?? '', ['Admin', 'Developer'], true)): ?>
        <?= view('Admin/schedule-form-modal', [
            'activeSubsidyTypes' => $activeSubsidyTypes ?? [],
            'barangayOptions'    => $barangayOptions ?? [],
            'sectorOptions'      => $batchSectorOptions ?? [],
            'scheduleColors'     => $scheduleColors ?? [],
            'venueSuggestions'   => $venueSuggestions ?? [],
        ]) ?>
    <?php endif; ?>
<?php elseif ($distributionTab === 'batches'): ?>
    <?= view('components/card', [
        'icon' => 'collection',
        'title' => 'Distribution Batches',
        'cardClass' => 'sector-management',
        'attrs' => 'data-table-paginate data-paginate-key="batches" data-paginate-label="batches"',
        'bodyView' => 'Admin/distribution-batches-body',
        'bodyData' => [
            'batches' => $batches ?? [],
            'activeBatch' => $activeBatch ?? null,
            'currentRole' => $currentRole ?? '',
        ],
        'footer' => view('components/table_footer', ['clientKey' => 'batches', 'entityLabel' => 'batches']),
    ]) ?>
<?php else: ?>
    <?= view('components/toolbar', [
        'isClient' => true,
        'formAria' => 'Search distributions',
        'searchPlaceholder' => 'Search the distributions log',
        'searchAttrs' => 'id="distSearch"',
        'clearAttrs' => 'id="distClear"',
    ]) ?>
    <?= view('components/card', [
        'icon' => 'clipboard-check-fill',
        'title' => 'All Distributions',
        'cardClass' => 'sector-management',
        'attrs' => 'data-table-paginate data-paginate-key="distributions" data-paginate-label="distributions"',
        'bodyView' => 'Admin/distribution-distributions-body',
        'bodyData' => ['distributions' => $distributions ?? []],
        'footer' => view('components/table_footer', ['clientKey' => 'distributions', 'entityLabel' => 'distributions']),
    ]) ?>

    <?php /* The toolbar keyword narrows the rows; paging and the page
             search are table-paginate.js (data-paginate-* above). */ ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const table  = document.getElementById('distTable');
      const search = document.getElementById('distSearch');
      const clear  = document.getElementById('distClear');
      const local  = document.getElementById('distLocalSearch');
      if (!table) return;
      const rows = Array.from(table.querySelectorAll('[data-paginate-row]'));

      const applyKeyword = () => {
        const q = (search.value || '').trim().toLowerCase();
        rows.forEach(r => {
          r.dataset.filtered = (q === '' || r.textContent.toLowerCase().includes(q)) ? '' : 'out';
        });
        window.refreshTablePagination('distributions', true);
      };

      search.addEventListener('input', applyKeyword);
      if (clear) clear.addEventListener('click', () => {
        search.value = '';
        if (local) local.value = '';
        applyKeyword();
      });
      applyKeyword();
    });
    </script>
<?php endif; ?>
