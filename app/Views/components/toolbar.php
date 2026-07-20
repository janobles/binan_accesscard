<?php
/**
 * Unified toolbar: keyword search, multi-column filter panel, and actions.
 * Can operate in Server mode (GET form submission) or Client mode (AJAX/JS DataTables).
 *
 * Variables:
 * - $isClient     bool    If true, omits method/action, adds data-records-client, and uses a button for Clear.
 * - $formAction   string  GET target URL (only used if isClient is false)
 * - $formAria     string  aria-label for the form
 * - $searchPlaceholder string entity-specific placeholder, e.g. "Search all sectors..."
 * - $searchName   string  name attribute for search input (default 'q')
 * - $keyword      string  current database keyword
 * - $searchAttrs  string  extra attributes for the search input (e.g. data-records-database-keyword)
 * - $clearUrl     string  full-reset URL (only used if isClient is false and clearAttrs is empty)
 * - $clearAttrs   string  attributes for clear button/link (e.g. data-records-clear or data-account-clear-filters)
 * - $pillsId      string  id for the components/filter_pills container
 * - $narrow       bool    add a type-to-narrow input for long option lists inside the dropdown
 * - $hiddenHtml   string  extra hidden inputs (e.g. per_page), already escaped
 * - $actionsHtml  string  record-action buttons (Add/Import), already escaped; separated from Search/Clear by a vertical rule
 * - $filterGroups array   filter groups: each is
 *                         ['name' => input name, 'label' => group heading,
 *                          'type' => 'radio' (default) or 'checkbox',
 *                          'scroll' => bool wrap options in a scrolling list,
 *                          'attrs' => string extra attributes for the input,
 *                          'options' => [['value','label','checked',
 *                            'pill' => pill text (omit for no-filter choices),
 *                            'default' => bool, the pill-x fallback], ...]]
 * - $formId       string  id attribute for the form
 * - $disableGenericFilterJs bool If true, omits data-records-filter-form
 */
$isClient = (bool) ($isClient ?? false);
$formAction = (string) ($formAction ?? '');
$formAria = (string) ($formAria ?? 'Search and filters');
$searchPlaceholder = (string) ($searchPlaceholder ?? 'Search all records...');
$searchName = (string) ($searchName ?? 'q');
$keyword = trim((string) ($keyword ?? ''));
$searchAttrs = (string) ($searchAttrs ?? '');
$clearUrl = (string) ($clearUrl ?? $formAction);
$clearAttrs = (string) ($clearAttrs ?? '');
$pillsId = (string) ($pillsId ?? 'recordsFilterPills');
$narrow = (bool) ($narrow ?? false);
$hiddenHtml = (string) ($hiddenHtml ?? '');
$actionsHtml = (string) ($actionsHtml ?? '');
$formId = (string) ($formId ?? '');
$disableGenericFilterJs = (bool) ($disableGenericFilterJs ?? false);

// Backwards compatibility with $radioGroups from old records_toolbar_server
$filterGroups = (array) ($filterGroups ?? $radioGroups ?? []);

$formTagAttrs = [
    'class' => 'row g-2 align-items-center mb-2',
    'role' => 'search',
    'aria-label' => esc($formAria, 'attr'),
    'data-records-pills' => esc($pillsId, 'attr'),
];
if ($formId !== '') {
    $formTagAttrs['id'] = esc($formId, 'attr');
}
if (!$disableGenericFilterJs) {
    $formTagAttrs['data-records-filter-form'] = '';
}
if ($isClient) {
    $formTagAttrs['data-records-client'] = '';
} else {
    $formTagAttrs['method'] = 'get';
    $formTagAttrs['action'] = esc($formAction, 'attr');
}

$buildAttrs = static function(array $attrs): string {
    $parts = [];
    foreach ($attrs as $k => $v) {
        $parts[] = $v === '' ? $k : $k . '="' . $v . '"';
    }
    return implode(' ', $parts);
};
?>
<form <?= $buildAttrs($formTagAttrs) ?>>
    <div class="col-12 col-lg">
        <div class="input-group">
            <input
                class="form-control"
                type="search"
                name="<?= esc($searchName, 'attr') ?>"
                value="<?= esc($keyword, 'attr') ?>"
                aria-label="<?= esc($searchPlaceholder, 'attr') ?>"
                placeholder="<?= esc($searchPlaceholder, 'attr') ?>"
                autocomplete="off"
                <?= $searchAttrs ?>
            >
            <button class="<?= btn('search') ?>" type="submit" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="col-12 col-lg-auto">
        <div class="dropdown" data-records-panel>
            <button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filters
            </button>
            <div class="dropdown-menu dropdown-menu-end records-filter-panel p-3 <?= count($filterGroups) > 2 ? 'records-filter-panel--wide' : '' ?>">
                <?php if ($narrow): ?>
                    <input class="form-control form-control-sm mb-2" type="search" placeholder="Search filters..." aria-label="Search filter options" data-records-narrow>
                <?php endif; ?>
                <div class="<?= count($filterGroups) > 2 ? 'row g-3' : 'd-flex flex-wrap gap-4' ?>">
                    <?php foreach ($filterGroups as $group): ?>
                        <?php 
                        $inputType = ($group['type'] ?? 'radio') === 'checkbox' ? 'checkbox' : 'radio';
                        $inputName = $inputType === 'checkbox' ? (string)$group['name'] . '[]' : (string)$group['name'];
                        ?>
                        <div class="<?= count($filterGroups) > 2 ? 'col-12 col-md-4' : '' ?>" data-records-filter="<?= esc((string) $group['name'], 'attr') ?>" data-records-group-label="<?= esc((string) $group['label'], 'attr') ?>">
                            <div class="fw-semibold small text-uppercase text-muted mb-1"><?= esc((string) $group['label']) ?></div>
                            <?php $scroll = (bool) ($group['scroll'] ?? false); ?>
                            <?php if ($scroll): ?><div class="records-filter-list overflow-auto"><?php endif; ?>
                            <?php foreach ((array) ($group['options'] ?? []) as $option): ?>
                                <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                    <input class="form-check-input m-0" type="<?= $inputType ?>"
                                        name="<?= esc($inputName, 'attr') ?>"
                                        value="<?= esc((string) $option['value'], 'attr') ?>"
                                        <?= isset($option['pill']) ? 'data-records-pill-label="' . esc((string) $option['pill'], 'attr') . '"' : '' ?>
                                        <?= ! empty($option['default']) ? 'data-records-default' : '' ?>
                                        <?= ! empty($option['checked']) ? 'checked' : '' ?>
                                        <?= $group['attrs'] ?? '' ?>>
                                    <span class="form-check-label text-wrap small"><?= esc((string) $option['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($scroll): ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?= $hiddenHtml ?>

    <div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Toolbar actions">
        <?php if ($isClient): ?>
            <button class="<?= btn('clear') ?> flex-fill" type="button" <?= $clearAttrs ?: 'data-records-clear' ?>>Clear</button>
        <?php else: ?>
            <a class="<?= btn('clear') ?> flex-fill" href="<?= esc($clearUrl, 'attr') ?>" <?= $clearAttrs ?>>Clear</a>
        <?php endif; ?>
        <?php if ($actionsHtml !== ''): ?>
        <div class="vr"></div>
        <?= $actionsHtml ?>
        <?php endif; ?>
    </div>
</form>
<?= view('components/filter_pills', [
    'id' => $pillsId,
]) ?>
