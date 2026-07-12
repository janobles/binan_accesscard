<?php
/**
 * Server-driven records toolbar: same anatomy as components/records_toolbar
 * (keyword grows | Filters dropdown | button group), but for pages that
 * reload on every change instead of redrawing a DataTable. Wired by
 * assets/js/dashboard/records-filter-panel.js (radios live in this GET form,
 * change = submit, pills render from server state). Render it ABOVE the
 * page's card, like Manage Records. Button classes come from btn().
 *
 * Variables:
 * - $formAction   string  GET target URL
 * - $formAria     string  aria-label for the form
 * - $keyword      string  current database keyword
 * - $clearUrl     string  full-reset URL (keyword + filters; page size survives)
 * - $pillsId      string  id for the components/filter_pills container
 * - $narrow       bool    add a type-to-narrow input for long option lists
 * - $hiddenHtml   string  extra hidden inputs (e.g. per_page), already escaped
 * - $actionsHtml  string  record-action buttons (Add/Import), already escaped;
 *                         separated from Search/Clear by a vertical rule
 * - $radioGroups  array   filter groups: each is
 *                         ['name' => input name, 'label' => group heading,
 *                          'scroll' => bool wrap options in a scrolling list,
 *                          'options' => [['value','label','checked',
 *                            'pill' => pill text (omit for no-filter choices),
 *                            'default' => bool, the pill-x fallback], ...]]
 */
$formAction = (string) ($formAction ?? '');
$formAria = (string) ($formAria ?? 'Search and filters');
$keyword = trim((string) ($keyword ?? ''));
$clearUrl = (string) ($clearUrl ?? $formAction);
$pillsId = (string) ($pillsId ?? 'recordsFilterPills');
$narrow = (bool) ($narrow ?? false);
$hiddenHtml = (string) ($hiddenHtml ?? '');
$actionsHtml = (string) ($actionsHtml ?? '');
$radioGroups = (array) ($radioGroups ?? []);
?>
<form class="row g-2 align-items-center mb-2" method="get" action="<?= esc($formAction, 'attr') ?>" role="search" aria-label="<?= esc($formAria, 'attr') ?>" data-records-filter-form data-records-pills="<?= esc($pillsId, 'attr') ?>">
    <div class="col-12 col-lg">
        <input
            class="form-control"
            type="search"
            name="q"
            value="<?= esc($keyword, 'attr') ?>"
            aria-label="Search entire database"
            placeholder="Search entire database..."
            autocomplete="off"
        >
    </div>

    <div class="col-12 col-lg-auto">
        <div class="dropdown" data-records-panel>
            <button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filters
            </button>
            <div class="dropdown-menu dropdown-menu-end records-filter-panel p-3">
                <?php if ($narrow): ?>
                    <input class="form-control form-control-sm mb-2" type="search" placeholder="Search filters..." aria-label="Search filter options" data-records-narrow>
                <?php endif; ?>
                <div class="row g-3">
                    <?php foreach ($radioGroups as $group): ?>
                        <div class="col-12 col-md" data-records-filter="<?= esc((string) $group['name'], 'attr') ?>" data-records-group-label="<?= esc((string) $group['label'], 'attr') ?>">
                            <div class="fw-semibold small text-uppercase text-muted mb-1"><?= esc((string) $group['label']) ?></div>
                            <?php $scroll = (bool) ($group['scroll'] ?? false); ?>
                            <?php if ($scroll): ?><div class="records-filter-list overflow-auto"><?php endif; ?>
                            <?php foreach ((array) $group['options'] as $option): ?>
                                <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                    <input class="form-check-input m-0" type="radio"
                                        name="<?= esc((string) $group['name'], 'attr') ?>"
                                        value="<?= esc((string) $option['value'], 'attr') ?>"
                                        <?= isset($option['pill']) ? 'data-records-pill-label="' . esc((string) $option['pill'], 'attr') . '"' : '' ?>
                                        <?= ! empty($option['default']) ? 'data-records-default' : '' ?>
                                        <?= ! empty($option['checked']) ? 'checked' : '' ?>>
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
        <button class="<?= btn('search') ?> flex-fill" type="submit">Search</button>
        <a class="<?= btn('clear') ?> flex-fill" href="<?= esc($clearUrl, 'attr') ?>">Clear</a>
        <?php if ($actionsHtml !== ''): ?>
        <div class="vr"></div>
        <?= $actionsHtml ?>
        <?php endif; ?>
    </div>
</form>
<?= view('components/filter_pills', [
    'id' => $pillsId,
]) ?>
