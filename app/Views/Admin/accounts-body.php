<?php
/**
 * Account management body: accounts table (the filter toolbar lives in
 * Admin/accounts.php). Rendered inside components/card by Admin/accounts.php —
 * see that file for the variable contract (accounts, canEditAccounts,
 * isDeveloper, isAdmin).
 */
use App\Libraries\ViewFormatter;
?>
        <?php /* Filter toolbar lives in accounts.php, above this card (Manage Records standard). */ ?>
        <div class="table-responsive">
            <table class="table account-table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $userId = (int) ($account['userID'] ?? 0);
                        $rawRole = (string) ($account['role'] ?? '');
                        $roleLabel = \App\Libraries\RoleAccess::normalizeRole($rawRole) ?? $rawRole;
                        $isActive = ViewFormatter::isActiveStatus($account['isactive'] ?? null);
                        $nextStatus = $isActive ? 'Disabled' : 'Enable';
                        $statusLabel = $isActive ? 'Active' : 'Inactive';
                        $statusClass = $isActive ? 'is-active' : 'is-disabled';
                        $statusFilter = $isActive ? 'active' : 'inactive';
                        $canEditRow = $canEditAccounts && in_array($rawRole, ['administrator', 'encoder', 'viewer', 'scanner'], true);
                        $canDeveloperToggle = $isDeveloper && in_array($rawRole, ['administrator', 'encoder', 'viewer', 'scanner'], true);
                        // Backend enableAccount/disableAccount only accept encoder/viewer;
                        // scanner toggling is Developer-only (see canDeveloperToggle above).
                        $canAdminToggle = $isAdmin && in_array($rawRole, ['encoder', 'viewer'], true);
                        $hasRowActions = $canEditRow || $canDeveloperToggle || $canAdminToggle;
                        ?>
                        <tr data-account-row data-account-username="<?= esc(mb_strtolower((string) ($account['username'] ?? '')), 'attr') ?>" data-account-role="<?= esc(mb_strtolower($rawRole), 'attr') ?>" data-account-status="<?= esc($statusFilter, 'attr') ?>">
                            <td><?= esc((string) ($account['username'] ?? '')) ?></td>
                            <td><?= esc($roleLabel) ?></td>
                            <td><span class="account-status-badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
                            <td class="text-end">
                                <div class="account-actions">
                                    <?php if ($hasRowActions): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary account-action-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Account actions">
                                                <i class="bi bi-three-dots" aria-hidden="true"></i>
                                            </button>
                                            <div class="dropdown-menu account-action-menu">
                                                <?php if ($canEditRow): ?>
                                                    <button class="dropdown-item js-open-account-edit-modal" type="button"
                                                            data-modal-url="<?= site_url('accounts/edit/' . $userId) ?>"
                                                            data-modal-title="Edit Account">
                                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                        <span>Edit</span>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($canDeveloperToggle): ?>
                                                    <form class="js-account-status-form" method="post" action="<?= site_url('developer/accounts/status') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' ' . $roleLabel . ' account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                                        <input type="hidden" name="status" value="<?= esc($nextStatus) ?>">
                                                        <button class="dropdown-item <?= $isActive ? 'text-danger' : 'text-success' ?>" type="submit">
                                                            <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i>
                                                            <span><?= $isActive ? 'Disable' : 'Enable' ?></span>
                                                        </button>
                                                    </form>
                                                <?php elseif ($canAdminToggle): ?>
                                                    <form class="js-account-status-form" method="post" action="<?= site_url($isActive ? 'admin/accounts/disable' : 'admin/accounts/enable') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' ' . $roleLabel . ' account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                                        <button class="dropdown-item <?= $isActive ? 'text-danger' : 'text-success' ?>" type="submit">
                                                            <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i>
                                                            <span><?= $isActive ? 'Disable' : 'Enable' ?></span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($accounts === []): ?>
                        <tr>
                            <td colspan="4" class="account-empty-state">No accounts found.</td>
                        </tr>
                    <?php else: ?>
                        <tr data-account-filter-empty hidden>
                            <td colspan="4" class="account-empty-state">No matching accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
