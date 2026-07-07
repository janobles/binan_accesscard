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
    <?= view('components/card', [
        'icon' => 'people',
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
