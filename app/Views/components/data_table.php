<?php
/**
 * SB Admin 1 data-table card — a table inside the standard card anatomy
 * (card-header icon+title > card-body table > optional card-footer),
 * matching the upstream SB Admin "DataTable Example" panel. Composes
 * components/card for that anatomy so there's one card shell in the codebase.
 *
 * Deterministic, props-only component. Cell values are RAW HTML: the caller
 * esc()'s every dynamic part when building rows (this is what lets rows carry
 * badges, buttons, and data-* attributes).
 *
 * Variables (all defaulted defensively):
 * - $title        string       header text
 * - $icon         string|null  bootstrap-icons name without "bi-" prefix (e.g. 'table')
 * - $footer       string|null  footer HTML (caller-escaped); null = no footer
 * - $columns      array        header cells: 'Label' or ['label' => 'Age', 'class' => 'text-end']
 * - $rows         array        list of rows; each row = list of raw-HTML cell strings,
 *                              or ['cells' => [...], 'attrs' => ' data-id="1"'] for row attributes
 * - $emptyMessage string       shown as a single full-width row when $rows === []
 * - $headerActions string|null raw HTML rendered right-aligned in the header
 *                              (caller-escaped), e.g. a small "View All" link
 * - $tableId      string|null  id attribute on the <table> (JS/DataTables hook)
 * - $tableClass   string       full class list for the <table>
 * - $id           string|null  id attribute on the card element
 * - $cardClass    string       extra classes on the card element
 */
$title = $title ?? '';
$icon = $icon ?? null;
$footer = $footer ?? null;
$columns = $columns ?? [];
$rows = $rows ?? [];
$emptyMessage = $emptyMessage ?? 'No records found.';
$headerActions = $headerActions ?? null;
$tableId = $tableId ?? null;
$tableClass = $tableClass ?? 'table mb-0';
$id = $id ?? null;
$cardClass = $cardClass ?? '';

ob_start();
?>
<div class="table-responsive">
    <table<?= $tableId !== null ? ' id="' . esc($tableId, 'attr') . '"' : '' ?> class="<?= esc($tableClass, 'attr') ?>">
        <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <?php if (is_array($column)): ?>
                <th scope="col"<?= isset($column['class']) ? ' class="' . esc($column['class'], 'attr') . '"' : '' ?>><?= esc($column['label'] ?? '') ?></th>
                    <?php else: ?>
                <th scope="col"><?= esc($column) ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $cells = is_array($row) && isset($row['cells']) ? $row['cells'] : $row; ?>
            <tr<?= is_array($row) && isset($row['attrs']) ? ' ' . $row['attrs'] : '' ?>>
                <?php foreach ($cells as $cell): ?>
                <td><?= $cell ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= count($columns) ?>" class="text-center text-muted"><?= esc($emptyMessage) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$tableHtml = ob_get_clean();

echo view('components/card', [
    'title' => $title,
    'icon' => $icon,
    'footer' => $footer,
    'headerActions' => $headerActions,
    'bodyHtml' => $tableHtml,
    'id' => $id,
    'cardClass' => $cardClass,
]);
