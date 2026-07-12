<?php
/**
 * Account management body: filter toolbar + accounts table.
 * Rendered inside components/card by Admin/accounts.php — see that file for
 * the variable contract (accounts, canCreateAccounts, canEditAccounts,
 * isDeveloper, isAdmin).
 */
use App\Libraries\ViewFormatter;
?>
        <div class="account-list-toolbar" role="search" aria-label="Filter accounts">
            <input class="form-control" type="search" data-account-search placeholder="Filter loaded results..." autocomplete="off" aria-label="Search accounts by username">
            <select class="form-select" data-account-level-filter aria-label="Filter by account level">
                <option value="">Select Level</option>
                <option value="administrator">Administrator</option>
                <option value="encoder">Encoder</option>
                <option value="viewer">Viewer</option>
                <option value="scanner">Scanner</option>
            </select>
            <select class="form-select" data-account-status-filter aria-label="Filter by account status">
                <option value="">Select Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button class="<?= btn('clear') ?> account-filter-clear" type="button" data-account-clear-filters>
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Clear</span>
            </button>
            <?php if ($canCreateAccounts): ?>
                <button class="<?= btn('add') ?> account-create-trigger js-open-account-create-modal" type="button" data-modal-url="<?= site_url('accounts/create') ?>" data-modal-title="Create Account">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>Create Account</span>
                </button>
            <?php endif; ?>
        </div>

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
                            <td><strong><?= esc((string) ($account['username'] ?? '')) ?></strong></td>
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
