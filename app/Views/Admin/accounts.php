<?php

$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$viewerAccounts = $viewerAccounts ?? [];
$scannerAccounts = $scannerAccounts ?? [];
$canCreateAccounts = (bool) ($canCreateAccounts ?? false);
$canEditAccounts = (bool) ($canEditAccounts ?? false);
$currentRole = (string) ($currentRole ?? '');
$isDeveloper = $currentRole === 'Developer';
$isAdmin = $currentRole === 'Admin';
$currentUserId = (int) session()->get('user_id');
// Admins and developers both manage every non-developer account now.
$accounts = array_merge($adminAccounts, $employeeAccounts, $viewerAccounts, $scannerAccounts);
?>

<div class="accounts-page" data-account-management>
    <?php /* Toolbar above the card, Manage Records standard. Client mode: the account list is
             fully loaded, so the keyword and the panel radios filter rows in the browser
             (accounts-modal.js) and records-filter-panel.js renders the pills — no reload. */ ?>
    <form class="row g-2 align-items-center mb-2" role="search" aria-label="Filter accounts" data-records-filter-form data-records-client data-records-pills="accountFilterPills">
        <div class="col-12 col-lg">
            <input class="form-control" type="search" data-account-search placeholder="Search accounts..." autocomplete="off" aria-label="Search accounts by username">
        </div>

        <div class="col-12 col-lg-auto">
            <div class="dropdown" data-records-panel>
                <button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-funnel" aria-hidden="true"></i> Filters
                </button>
                <div class="dropdown-menu dropdown-menu-end records-filter-panel p-3">
                    <div class="d-flex flex-wrap gap-4">
                        <div data-records-filter="level" data-records-group-label="Level">
                            <div class="fw-semibold small text-uppercase text-muted mb-1">Level</div>
                            <?php $accountLevels = ['' => 'All levels', 'administrator' => 'Administrator', 'encoder' => 'Encoder', 'viewer' => 'Viewer', 'scanner' => 'Scanner']; ?>
                            <?php foreach ($accountLevels as $value => $label): ?>
                                <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                    <input class="form-check-input m-0" type="radio" name="account_level" value="<?= esc((string) $value, 'attr') ?>" data-account-level-filter
                                        <?= $value === '' ? 'data-records-default checked' : 'data-records-pill-label="' . esc($label, 'attr') . '"' ?>>
                                    <span class="form-check-label small"><?= esc($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div data-records-filter="status" data-records-group-label="Status">
                            <div class="fw-semibold small text-uppercase text-muted mb-1">Status</div>
                            <?php $accountStatuses = ['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']; ?>
                            <?php foreach ($accountStatuses as $value => $label): ?>
                                <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                    <input class="form-check-input m-0" type="radio" name="account_status" value="<?= esc((string) $value, 'attr') ?>" data-account-status-filter
                                        <?= $value === '' ? 'data-records-default checked' : 'data-records-pill-label="' . esc($label, 'attr') . '"' ?>>
                                    <span class="form-check-label small"><?= esc($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Toolbar actions">
            <button class="<?= btn('clear') ?> flex-fill" type="button" data-account-clear-filters>Clear</button>
            <?php if ($canCreateAccounts): ?>
            <div class="vr"></div>
            <button class="<?= btn('add') ?> flex-fill js-open-account-create-modal" type="button" data-modal-url="<?= site_url('accounts/create') ?>" data-modal-title="Create Account">Create Account</button>
            <?php endif; ?>
        </div>
    </form>
    <?= view('components/filter_pills', ['id' => 'accountFilterPills']) ?>

    <?= view('components/card', [
        'icon' => 'people-fill',
        'title' => 'Account Management',
        'cardClass' => 'account-card',
        'attrs' => 'aria-labelledby="accounts-title"',
        'bodyView' => 'Admin/accounts-body',
        'bodyData' => [
            'accounts' => $accounts,
            'canEditAccounts' => $canEditAccounts,
            'isDeveloper' => $isDeveloper,
            'isAdmin' => $isAdmin,
        ],
    ]) ?>
</div>
