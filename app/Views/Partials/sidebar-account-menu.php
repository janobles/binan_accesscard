<?php
/**
 * Account dropdown in the sidebar footer, styled like Shadcn.
 *
 * $user, $username, $accountLevelLabel, $accountSettingsUrl, $accountSettingsMode
 */

$user = $user ?? [];
$username = (string) ($user['username'] ?? 'User');
$accountLevelLabel = (string) ($accountLevelLabel ?? 'Account');
$accountSettingsUrl = (string) ($accountSettingsUrl ?? site_url('account/profile'));
$accountSettingsMode = (string) ($accountSettingsMode ?? 'modal');

$topbarDetails = \App\Libraries\ViewFormatter::parseFullDescription((string) ($user['full_description'] ?? ''));
$topbarFullName = trim(implode(' ', array_filter([
    $topbarDetails['first_name'] ?? '',
    \App\Libraries\ViewFormatter::middleInitial((string) ($topbarDetails['middle_name'] ?? '')),
    $topbarDetails['last_name'] ?? '',
    $topbarDetails['suffix'] ?? '',
])));
$topbarFullName = $topbarFullName !== '' ? $topbarFullName : $username;

$initials = '';
foreach (explode(' ', $topbarFullName) as $word) {
    if (mb_strlen($word) > 0) {
        $initials .= mb_substr($word, 0, 1);
    }
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
?>
<div class="dropup w-100">
    <button class="btn btn-light w-100 d-flex align-items-center justify-content-between p-1 px-2 border-0 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent;">
        <div class="d-flex align-items-center overflow-hidden">
            <div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white" style="width: 28px; height: 28px; background-color: var(--token-primary-green); font-size: 0.75rem; font-weight: 600;">
                <?= esc($initials) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-truncate" style="font-size: 0.8rem; color: var(--token-text-primary); line-height: 1.2;"><?= esc($username) ?></div>
                <div class="text-truncate" style="font-size: 0.7rem; color: var(--token-text-secondary); line-height: 1.2;"><?= esc($accountLevelLabel) ?></div>
            </div>
        </div>
        <i class="bi bi-chevron-expand text-muted ms-2" style="font-size: 0.85rem;"></i>
    </button>
    <div class="dropdown-menu shadow-sm border rounded p-2 mb-2 w-100" style="min-width: 15rem; z-index: 1050;">
        <div class="d-flex align-items-center p-2 mb-1">
            <div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background-color: var(--token-primary-green); font-size: 1rem; font-weight: 600;">
                <?= esc($initials) ?>
            </div>
            <div class="overflow-hidden">
                <strong class="d-block text-truncate" style="font-size: 0.875rem; color: var(--token-text-primary);"><?= esc(mb_strtoupper($topbarFullName, 'UTF-8')) ?></strong>
                <small class="d-block text-truncate" style="font-size: 0.75rem; color: var(--token-text-secondary);"><?= esc($accountLevelLabel) ?></small>
            </div>
        </div>
        <hr class="dropdown-divider my-1">
        
        <?php if ($accountSettingsMode === 'link'): ?>
            <a href="<?= esc($accountSettingsUrl, 'attr') ?>" class="dropdown-item rounded d-flex align-items-center py-2 text-dark" style="font-size: 0.875rem;">
                <i class="bi bi-gear me-2"></i>Account Settings
            </a>
        <?php else: ?>
            <button type="button" class="dropdown-item rounded d-flex align-items-center py-2 text-dark js-open-my-account-modal" data-modal-url="<?= esc($accountSettingsUrl, 'attr') ?>" data-modal-title="My Account" style="font-size: 0.875rem;">
                <i class="bi bi-gear me-2"></i>Account Settings
            </button>
        <?php endif; ?>
        
        <hr class="dropdown-divider my-1">
        <a href="<?= site_url('logout') ?>" class="dropdown-item rounded d-flex align-items-center py-2 text-dark js-logout-link" style="font-size: 0.875rem;">
            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
        </a>
    </div>
</div>
