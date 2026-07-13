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
$accounts = array_merge($adminAccounts, $employeeAccounts, $viewerAccounts, $scannerAccounts);
?>

<div class="accounts-page" data-account-management>
    <?php /* Toolbar above the card, Manage Records standard. Client mode: the account list is
             fully loaded, so the keyword and the panel radios filter rows in the browser
             (accounts-modal.js) and records-filter-panel.js renders the pills — no reload. */ ?>
    <?php
    $accountLevelsGroup = [
        'name' => 'account_level',
        'label' => 'Level',
        'type' => 'radio',
        'options' => [
            ['value' => '', 'label' => 'All levels', 'pill' => 'All levels', 'checked' => true, 'default' => true],
            ['value' => 'administrator', 'label' => 'Administrator', 'pill' => 'Administrator', 'checked' => false],
            ['value' => 'encoder', 'label' => 'Encoder', 'pill' => 'Encoder', 'checked' => false],
            ['value' => 'viewer', 'label' => 'Viewer', 'pill' => 'Viewer', 'checked' => false],
            ['value' => 'scanner', 'label' => 'Scanner', 'pill' => 'Scanner', 'checked' => false],
        ],
        'attrs' => 'data-account-level-filter',
    ];

    $accountStatusesGroup = [
        'name' => 'account_status',
        'label' => 'Status',
        'type' => 'radio',
        'options' => [
            ['value' => '', 'label' => 'All statuses', 'pill' => 'All statuses', 'checked' => true, 'default' => true],
            ['value' => 'active', 'label' => 'Active', 'pill' => 'Active', 'checked' => false],
            ['value' => 'inactive', 'label' => 'Inactive', 'pill' => 'Inactive', 'checked' => false],
        ],
        'attrs' => 'data-account-status-filter',
    ];

    $actionsHtml = '';
    if ($canCreateAccounts) {
        $actionsHtml = '<button class="' . btn('add') . ' flex-fill js-open-account-create-modal" type="button" data-modal-url="' . site_url('accounts/create') . '" data-modal-title="Create Account">Create Account</button>';
    }
    ?>
    <?= view('components/toolbar', [
        'isClient' => true,
        'formAria' => 'Filter accounts',
        'searchPlaceholder' => 'Search accounts...',
        'searchName' => 'q',
        'searchAttrs' => 'data-account-search aria-label="Search accounts by username"',
        'clearAttrs' => 'data-account-clear-filters',
        'pillsId' => 'accountFilterPills',
        'actionsHtml' => $actionsHtml,
        'filterGroups' => [$accountLevelsGroup, $accountStatusesGroup],
    ]) ?>
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
