<?php

$developerAccounts = $developerAccounts ?? [];
$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$viewerAccounts = $viewerAccounts ?? [];
$scannerAccounts = $scannerAccounts ?? [];
$canCreateAccounts = (bool) ($canCreateAccounts ?? false);
$canEditAccounts = (bool) ($canEditAccounts ?? false);
$currentRole = (string) ($currentRole ?? '');
$isDeveloper = $currentRole === 'Developer';
$isAdmin = $currentRole === 'Admin';
$accounts = array_merge($developerAccounts, $adminAccounts, $employeeAccounts, $viewerAccounts, $scannerAccounts);
?>

<div class="accounts-page" data-account-management>
    <?= view('components/card', [
        'icon' => 'people-fill',
        'title' => 'Account Management',
        'cardClass' => 'account-card',
        'attrs' => 'aria-labelledby="accounts-title"',
        'bodyView' => 'Admin/accounts-body',
        'bodyData' => [
            'accounts' => $accounts,
            'canCreateAccounts' => $canCreateAccounts,
            'canEditAccounts' => $canEditAccounts,
            'isDeveloper' => $isDeveloper,
            'isAdmin' => $isAdmin,
        ],
    ]) ?>
</div>
