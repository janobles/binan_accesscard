<?php
/**
 * Audit trails body: dual search bars + audit table.
 * Rendered inside components/card by Admin/audit-trails.php — see that file
 * for the variable contract (listRoute, searchTerm, auditAction,
 * auditActionOptions, perPage, perPageOptions, recentAudits,
 * hasSearchFilters, auditClearUrl).
 */
?>
    <?php /* Bar 1 (database search) lives in audit-trails.php, above this card (Manage Records standard). */ ?>
    <?= view('components/table_controls', [
        'searchId' => 'auditLocalSearch',
        'searchAria' => 'Search shown audit logs',
        'searchFormAttrs' => 'data-lookup-search',
        'searchInputAttrs' => 'data-lookup-search-input',
        'sizeId' => 'auditPerPage',
        'sizeAction' => site_url($listRoute),
        'sizeHiddenHtml' => ($searchTerm !== '' ? '<input type="hidden" name="q" value="' . esc($searchTerm, 'attr') . '">' : '')
            . ($auditAction !== '' ? '<input type="hidden" name="action" value="' . esc($auditAction, 'attr') . '">' : ''),
        'perPage' => $perPage,
        'perPageOptions' => $perPageOptions,
    ]) ?>

    <?php
    // Raw user-agent strings are unreadable at a glance; show "Browser · OS"
    // with the full string in a hover tooltip (and in the detail modal).
    $formatUaShort = static function (string $ua): string {
        if ($ua === '') {
            return '';
        }
        $browser = 'Other browser';
        if (preg_match('/Edg(?:e|A|iOS)?\/(\d+)/', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/OPR\/(\d+)/', $ua, $m)) {
            $browser = 'Opera ' . $m[1];
        } elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/Chrome\/(\d+)/', $ua, $m)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (stripos($ua, 'Safari') !== false) {
            $browser = 'Safari';
        }
        $os = '';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Mac OS X') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return $browser . ($os !== '' ? ' · ' . $os : '');
    };

    // USER_LOGIN -> "User Login" for the pill; the raw enum stays in the
    // action filter values.
    $formatActionLabel = static fn (string $action): string => ucwords(strtolower(str_replace('_', ' ', $action)));
    ?>
    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                    <th scope="col">Device</th>
                    <th scope="col">Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <?php
                        $auditTs = strtotime((string) ($audit['dt_created'] ?? ''));
                        $auditUa = trim((string) ($audit['user_agent'] ?? ''));
                        $auditUsername = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
                        $auditRole = trim((string) ($audit['user_role'] ?? ''));
                        $auditRole = \App\Libraries\RoleAccess::auditRoleLabel($auditRole) ?? $auditRole;
                    ?>
                    <?php /* The whole row is the detail trigger (js-audit-detail) — audit-detail-modal.js
                             reads data-full and surfaces the narrative in that modal. */ ?>
                    <tr class="audit-row js-audit-detail" tabindex="0" role="button" aria-label="View audit log details"
                        data-full="<?= esc((string) ($audit['full_description'] ?? ''), 'attr') ?>">
                        <td class="audit-user">
                            <strong><?= esc($auditUsername) ?></strong>
                            <?php if ($auditRole !== ''): ?><small class="text-muted d-block"><?= esc($auditRole) ?></small><?php endif; ?>
                        </td>
                        <td><span class="audit-action-pill" title="<?= esc($formatActionLabel((string) ($audit['user_action'] ?? '')), 'attr') ?>"><?= esc($formatActionLabel((string) ($audit['user_action'] ?? ''))) ?></span></td>
                        <td class="audit-desc"><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td class="audit-ua"><?= $auditUa === '' ? '<span class="text-muted">—</span>' : '<span title="' . esc($auditUa, 'attr') . '">' . esc($formatUaShort($auditUa)) . '</span>' ?></td>
                        <td class="audit-when"><?= $auditTs ? esc(date('M j, Y h:i A', $auditTs)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?> 
                    <tr><td colspan="5" class="audit-trails-empty audit-empty-state"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
