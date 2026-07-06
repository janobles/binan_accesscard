<?php
$user = $user ?? [];
$username = (string) ($user['username'] ?? 'User');
$accountLevelLabel = (string) ($accountLevelLabel ?? 'Account');
$accountSettingsUrl = (string) ($accountSettingsUrl ?? site_url('account/profile'));
$accountSettingsMode = (string) ($accountSettingsMode ?? 'modal');

$topbarDetails = \App\Libraries\ViewFormatter::parseFullDescription((string) ($user['full_description'] ?? ''));
$topbarFullName = trim(implode(' ', array_filter([
    $topbarDetails['first_name'] ?? '',
    $topbarDetails['middle_name'] ?? '',
    $topbarDetails['last_name'] ?? '',
    $topbarDetails['suffix'] ?? '',
])));
$topbarFullName = $topbarFullName !== '' ? $topbarFullName : $username;
?>
<li class="nav-item dropdown topbar-account">
    <button class="nav-link topbar-user topbar-account-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end topbar-account-menu">
        <div class="topbar-account-summary">
            <img class="topbar-account-avatar" src="<?= esc(base_url('assets/image/default-profile.svg'), 'attr') ?>" alt="Profile picture">
            <strong><?= esc(mb_strtoupper($topbarFullName, 'UTF-8')) ?></strong>
            <small><?= esc($accountLevelLabel) ?></small>
        </div>
        <?php if ($accountSettingsMode === 'link'): ?>
            <a href="<?= esc($accountSettingsUrl, 'attr') ?>" class="dropdown-item"><i class="bi bi-gear" aria-hidden="true"></i><span>Account Settings</span></a>
        <?php else: ?>
            <button type="button" class="dropdown-item js-open-my-account-modal" data-modal-url="<?= esc($accountSettingsUrl, 'attr') ?>" data-modal-title="My Account"><i class="bi bi-gear" aria-hidden="true"></i><span>Account Settings</span></button>
        <?php endif; ?>
        <a href="<?= site_url('logout') ?>" class="dropdown-item js-logout-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign Out</span></a>
    </div>
</li>
