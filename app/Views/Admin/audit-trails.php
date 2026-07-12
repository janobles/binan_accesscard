<?php
$recentAudits       = $recentAudits ?? [];
$searchTerm         = $searchTerm ?? '';
$searchFilters      = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$auditListData      = $auditListData ?? [];
$hasSearchFilters   = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];

// Pagination + page-size bundle (from DashboardPageBuilder::buildAuditListData).
$listRoute      = (string) ($auditListData['listRoute'] ?? 'admin/audit-trails');
$auditAction    = trim((string) ($searchFilters['action'] ?? ''));
$perPage        = (int) ($auditListData['perPage'] ?? 50);
$perPageOptions = ($auditListData['perPageOptions'] ?? []) ?: [10, 25, 50, 100];
$page           = (int) ($auditListData['page'] ?? 1);
$totalPages     = (int) ($auditListData['totalPages'] ?? 1);
$totalRows      = (int) ($auditListData['totalRows'] ?? count($recentAudits));
$fromRecord     = (int) ($auditListData['fromRecord'] ?? 0);
$toRecord       = (int) ($auditListData['toRecord'] ?? 0);

// Page URL preserving the database keyword + action filter + page size.
$auditPageUrl = static function (int $targetPage) use ($listRoute, $searchTerm, $auditAction, $perPage): string {
    $params = array_filter([
        'q'        => $searchTerm,
        'action'   => $auditAction,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" resets the whole toolbar (keyword + action filter, back to page 1)
// per the one-role-per-control rule; only the page size survives.
$auditClearUrl = static function () use ($listRoute, $perPage): string {
    $params = $perPage !== 50 ? ['per_page' => (string) $perPage] : [];

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

$formatAuditMember = static function (array $audit): string {
    $memberName = trim((string) ($audit['member_name'] ?? ''));

    if ($memberName === '') {
        $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
    }

    return $memberName === '' ? '-' : $memberName;
};

$formatAuditUser = static function (array $audit): string {
    $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
    $role     = trim((string) ($audit['user_role'] ?? ''));
    $role     = \App\Libraries\RoleAccess::auditRoleLabel($role) ?? $role;

    return $role === '' ? $username : $username . ' (' . $role . ')';
};
?>

<?php /* Jade-style audit panel reusing the Lookups dual-search layout (records-* classes,
         managerecord.css). Bar 1 = database search (server GET) with the action filter in the
         Filters dropdown panel (records-filter-panel.js live-apply + pills). Bar 2 =
         page-size + client-side local "Search:" filter via data-lookup-search (lookup-search.js,
         scoped by data-audit-management-root). */ ?>
<?php
$auditFooter = $totalRows > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $auditPageUrl(max(1, $page - 1)),
    'nextUrl' => $auditPageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'clock-history',
    'title' => 'Audit Trails',
    'attrs' => 'aria-label="Audit trails" data-audit-management-root',
    'cardClass' => 'audit-trails',
    'bodyView' => 'Admin/audit-trails-body',
    'bodyData' => [
        'listRoute' => $listRoute,
        'searchTerm' => $searchTerm,
        'auditAction' => $auditAction,
        'auditActionOptions' => $auditActionOptions,
        'perPage' => $perPage,
        'perPageOptions' => $perPageOptions,
        'recentAudits' => $recentAudits,
        'hasSearchFilters' => $hasSearchFilters,
        'formatAuditUser' => $formatAuditUser,
        'auditClearUrl' => $auditClearUrl,
    ],
    'footer' => $auditFooter,
]) ?>
