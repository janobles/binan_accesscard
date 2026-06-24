<?php

namespace App\Models;

use App\Libraries\SectorIds;
use App\Models\Concerns\NormalizesIds;
use App\Models\Concerns\RecordStatus;
use App\Models\Concerns\ResolvesMemberNames;
use App\Models\Concerns\ResolvesSectorNames;
use App\Models\Concerns\ResolvesUserNames;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Centralizes search queries used by records, accounts, and audit trail pages.
 */
class SearchModel
{
    use NormalizesIds;
    use ResolvesMemberNames;
    use ResolvesSectorNames;
    use ResolvesUserNames;

    private BaseConnection $db;

    /** Accepts an optional DB connection (defaults to the shared one) for testing. */
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * FIRST (quick) Manage Records search: family HEADS only, matched by name/
     * contact/relationship and sector. Applies the sector + date filters and
     * resolves sector names. Frontend: the quick search box on Manage Records.
     */
    public function families(string $keyword = '', array $filters = [], int $limit = 25): array
    {
        if (! $this->hasFamilySearchTables()) {
            return [];
        }

        $limit = max(1, $limit);
        $builder = $this->db->table('member')
            ->select('memberID, firstname, middlename, lastname, contactnumber, relationship, headID, sectorID, dt_created, dt_updated')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyMemberKeyword($builder, $keyword, '', ['sectorID'], 'sectorID');
        }

        $sectorIds = $this->normalizeFilterList($filters['sectorID'] ?? []);

        if ($sectorIds !== []) {
            $builder->groupStart();
            foreach ($sectorIds as $index => $sectorId) {
                if ($index === 0) {
                    $builder->where(SectorIds::containsCondition((int) $sectorId, 'sectorID'), null, false);
                    continue;
                }

                $builder->orWhere(SectorIds::containsCondition((int) $sectorId, 'sectorID'), null, false);
            }
            $builder->groupEnd();
        }

        $this->applyDateRange($builder, 'dt_created', $filters);

        $rows = $builder
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    /**
     * SECOND ("search the whole database") bar of the Manage Records tab.
     *
     * Unlike families() this is NOT limited to family heads -- it searches every
     * member (heads AND non-head family members). The keyword also matches sector
     * names and service/program names, so a person can be found by the sector or
     * assistance they are tied to. Each row carries its head ("belongs to") name,
     * resolved sector names, and resolved service names.
     *
     * Called from App\Libraries\DashboardPageBuilder::buildMemberListData() and
     * Employee\WorkspaceModel::recordListData() when the deep search box (deep_q) is used.
     */
    public function allMembers(string $keyword = '', array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $rows = $this->allMembersBuilder($keyword, $filters)
            ->orderBy('m.lastname', 'ASC')
            ->orderBy('m.firstname', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return $this->withServiceNames($this->withSectorNames($rows));
    }

    /**
     * Row count for the deep member search (drives the deep-search pagination).
     */
    public function countAllMembers(string $keyword = '', array $filters = []): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->allMembersBuilder($keyword, $filters)->countAllResults();
    }

    /**
     * Shared query for allMembers()/countAllMembers(): every member (incl. non-heads),
     * left-joined to its head, with keyword across member fields + sector + service,
     * plus the Manage Records filters (sectorID, date, active/archived status).
     */
    private function allMembersBuilder(string $keyword, array $filters): BaseBuilder
    {
        $builder = $this->db->table('member m')
            ->select('m.memberID, m.firstname, m.middlename, m.lastname, m.suffix, m.birthday, m.contactnumber, m.relationship, m.address, m.headID, m.sectorID, m.dt_created, m.dt_deleted, h.firstname AS head_firstname, h.lastname AS head_lastname, h.suffix AS head_suffix')
            ->join('member h', 'h.memberID = m.headID', 'left');

        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        if ($status === RecordStatus::ARCHIVED) {
            $builder->where('m.dt_deleted IS NOT NULL', null, false);
        } elseif ($status !== RecordStatus::ALL) {
            $builder->where('m.dt_deleted IS NULL', null, false);
        }

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            // Match by service/program name -> the members assigned that service.
            $serviceMemberIds = $this->memberIdsForServiceKeyword($keyword);

            $this->applyMemberKeyword($builder, $keyword, 'm.', ['address', 'religion', 'job'], 'm.sectorID', $serviceMemberIds);
        }

        $sectorIds = $this->normalizeFilterList($filters['sectorID'] ?? []);

        if ($sectorIds !== []) {
            $builder->groupStart();
            foreach ($sectorIds as $index => $sectorId) {
                if ($index === 0) {
                    $builder->where(SectorIds::containsCondition((int) $sectorId, 'm.sectorID'), null, false);
                    continue;
                }

                $builder->orWhere(SectorIds::containsCondition((int) $sectorId, 'm.sectorID'), null, false);
            }
            $builder->groupEnd();
        }

        $barangays = $this->normalizeFilterList($filters['barangay'] ?? [], false);

        if ($barangays !== []) {
            // No barangay column exists; barangay is stored as the trailing part
            // of the combined `address` value ("address, barangay" or just
            // "barangay"). Mirror splitAddressBarangay(): exact match or a
            // ", barangay" suffix. OR across the selected barangays.
            $builder->groupStart();
            foreach ($barangays as $index => $barangay) {
                $open = $index === 0 ? 'groupStart' : 'orGroupStart';
                $builder->{$open}()
                    ->where('m.address', $barangay)
                    ->orLike('m.address', ', ' . $barangay, 'before')
                    ->groupEnd();
            }
            $builder->groupEnd();
        }

        $this->applyDateRange($builder, 'm.dt_created', $filters);

        return $builder;
    }

    /**
     * Adds a member keyword clause to a query. Each whitespace token must match one
     * of the name columns (AND across tokens, OR across firstname/middlename/lastname),
     * so a full "Firstname Lastname" matches even though the words live in different
     * columns. The whole keyword is still matched against the non-name fields
     * (contact/relationship + $likeColumns + sector + assigned services) as an OR
     * branch, so single-word and non-name searches behave as before. $prefix is the
     * table alias prefix ('' or 'm.'); $sectorColumn is the sectorID column to test.
     */
    private function applyMemberKeyword(BaseBuilder $builder, string $keyword, string $prefix, array $likeColumns, string $sectorColumn, array $serviceMemberIds = []): void
    {
        $tokens = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [$keyword];

        $builder->groupStart();

        // Name tokens, AND-ed together.
        $builder->groupStart();
        foreach ($tokens as $token) {
            $builder->groupStart()
                ->like($prefix . 'firstname', $token)
                ->orLike($prefix . 'middlename', $token)
                ->orLike($prefix . 'lastname', $token)
                ->orLike($prefix . 'suffix', $token)
                ->groupEnd();
        }
        $builder->groupEnd();

        // Whole-keyword matches on the non-name fields, OR-ed with the name match.
        $builder->orGroupStart()
            ->like($prefix . 'contactnumber', $keyword)
            ->orLike($prefix . 'relationship', $keyword);

        foreach ($likeColumns as $field) {
            if ($this->db->fieldExists($field, 'member')) {
                $builder->orLike($prefix . $field, $keyword);
            }
        }

        // Match by sector name -> sector IDs -> JSON array contains the ID.
        foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
            $builder->orWhere(SectorIds::containsCondition($sectorId, $sectorColumn), null, false);
        }

        if ($serviceMemberIds !== []) {
            $builder->orWhereIn($prefix . 'memberID', $serviceMemberIds);
        }

        $builder->groupEnd();

        $builder->groupEnd();
    }

    /**
     * Searches Admin/Employee accounts by username/role/status with optional role
     * and active-status filters. Frontend: the Account Management search/filter UI.
     */
    public function staffAccounts(string $keyword = '', array $filters = [], int $limit = 100): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        $limit = max(1, $limit);
        // 'administrator'/'encoder' are the DB enum values for the Admin/Employee
        // roles; these queries use the raw enum to match the users.account_level
        // column, aliased back to `role` so downstream callers keep the same key.
        $builder = $this->db->table('users')
            ->select('userID, username, account_level AS role, isactive, dt_created')
            ->whereIn('account_level', ['administrator', 'encoder', 'viewer']);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('username', $keyword)
                ->orLike('account_level', $keyword)
                ->orLike('isactive', $keyword)
                ->groupEnd();
        }

        $role = $this->normalizeKeyword((string) ($filters['role'] ?? ''));

        if (in_array($role, ['administrator', 'encoder', 'viewer'], true)) {
            $builder->where('account_level', $role);
        }

        $status = $this->normalizeKeyword((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $this->applyActiveStatusFilter($builder, $status);
        }

        $rows = $builder
            ->orderBy('account_level', 'ASC')
            ->orderBy('username', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $rows;
    }

    /**
     * Builds the filtered audit query shared by the list + count methods so a
     * page's row set and its total always match. Applies (in order): the optional
     * per-user scope, the Developer-visibility guard (admin views only), the
     * keyword search, and the action/date filters. Ordering/limit/offset are added
     * by the callers.
     *
     * @param int|null $userId When set, scopes to that user's own rows (employee
     *                         Activity) — the Developer guard is then skipped.
     */
    private function auditSearchBuilder(string $keyword, array $filters, bool $includeDeveloper, ?int $userId = null): BaseBuilder
    {
        $builder = $this->auditTrailBuilder();

        if ($userId !== null) {
            // AND-ed scope, kept outside the keyword OR-group below.
            $builder->where('audit_trails.userID', $userId);
        }

        // Developer audit rows (NULL userID, no users row) stay hidden from
        // non-developer viewers so the other roles never learn a Developer exists.
        // Only relevant to the all-users admin view ($userId === null).
        if ($userId === null && ! $includeDeveloper) {
            // Hide only the Developer's own rows; failed logins and system errors
            // (logged without a users row) still surface to admins.
            $builder->where(
                "(audit_trails.userID IS NOT NULL OR audit_trails.user_action IN ('LOGIN_FAILED','SYSTEM_ERROR'))",
                null,
                false
            );
        }

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $this->applyAuditFilters($builder, $filters);

        return $builder;
    }

    /**
     * Searches all audit entries by action/description/IP and by related user or
     * member name, with action + date filters. Frontend: the admin Audit Trails
     * search/filter UI.
     */
    public function auditTrails(string $keyword = '', array $filters = [], int $limit = 50, bool $includeDeveloper = false, int $offset = 0): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $rows = $this->auditSearchBuilder($keyword, $filters, $includeDeveloper)
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->getResultArray();

        return $this->withAuditNames($rows);
    }

    /** Total audit rows matching the keyword/action/date filter (for pagination). */
    public function countAuditTrails(string $keyword = '', array $filters = [], bool $includeDeveloper = false): int
    {
        if (! $this->hasAuditSearchTables()) {
            return 0;
        }

        return $this->auditSearchBuilder($keyword, $filters, $includeDeveloper)->countAllResults();
    }

    /**
     * Same as auditTrails() but scoped to one user. Frontend: the employee
     * Activity page search/filter.
     */
    public function auditTrailsByUser(int $userId, string $keyword = '', array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $rows = $this->auditSearchBuilder($keyword, $filters, false, $userId)
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->getResultArray();

        return $this->withAuditNames($rows);
    }

    /** Total audit rows for one user matching the filter (for pagination). */
    public function countAuditTrailsByUser(int $userId, string $keyword = '', array $filters = []): int
    {
        if (! $this->hasAuditSearchTables()) {
            return 0;
        }

        return $this->auditSearchBuilder($keyword, $filters, false, $userId)->countAllResults();
    }

    /**
     * Returns the distinct list of audit action types, used to populate the audit
     * "action" filter dropdown on the frontend.
     */
    public function auditActions(): array
    {
        if (! $this->db->tableExists('audit_trails')) {
            return [];
        }

        return array_column(
            $this->db->table('audit_trails')
                ->select('TRIM(user_action) AS user_action', false)
                ->where('user_action IS NOT NULL')
                ->where("TRIM(user_action) != ''", null, false)
                ->groupBy('TRIM(user_action)')
                ->orderBy('TRIM(user_action)', 'ASC', false)
                ->get()
                ->getResultArray(),
            'user_action'
        );
    }

    /** Base query for audit searches (selects all audit columns). */
    private function auditTrailBuilder(): BaseBuilder
    {
        return $this->db->table('audit_trails')
            ->select('audit_trails.*');
    }

    /**
     * Adds the keyword search clause to an audit query: matches action/description/
     * IP directly, plus audits tied to users or members whose names match.
     */
    private function applyAuditSearch(BaseBuilder $builder, string $keyword): void
    {
        $builder->groupStart()
            ->like('audit_trails.user_action', $keyword)
            ->orLike('audit_trails.description', $keyword)
            ->orLike('audit_trails.full_description', $keyword)
            ->orLike('audit_trails.ip_address', $keyword);

        $userIds = $this->userIdsForKeyword($keyword);

        if ($userIds !== []) {
            $builder->orWhereIn('audit_trails.userID', $userIds);
        }

        $memberIds = $this->memberIdsForKeyword($keyword);

        if ($memberIds !== []) {
            $builder->orWhereIn('audit_trails.memberID', $memberIds);
        }

        $builder->groupEnd();
    }

    /** Applies the audit action dropdown + date filters to an audit query. */
    private function applyAuditFilters(BaseBuilder $builder, array $filters): void
    {
        $action = $this->normalizeKeyword((string) ($filters['action'] ?? ''));

        if ($action !== '') {
            $builder->where('TRIM(audit_trails.user_action) = ' . $this->db->escape($action), null, false);
        }

        $this->applyDateRange($builder, 'audit_trails.dt_created', $filters);
    }

    /**
     * Applies a date filter to a query: either a single `date` (whole day) or a
     * `date_from`/`date_to` range. Shared by family, member, and audit searches.
     */
    private function applyDateRange(BaseBuilder $builder, string $column, array $filters): void
    {
        $date = $this->normalizeDate((string) ($filters['date'] ?? ''));

        if ($date !== '') {
            $builder
                ->where($column . ' >=', $date . ' 00:00:00')
                ->where($column . ' <=', $date . ' 23:59:59');

            return;
        }

        $dateFrom = $this->normalizeDate((string) ($filters['date_from'] ?? ''));
        $dateTo = $this->normalizeDate((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '') {
            $builder->where($column . ' >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '') {
            $builder->where($column . ' <=', $dateTo . ' 23:59:59');
        }
    }

    /**
     * Filters accounts by active/disabled, tolerating both the Enable/Disabled
     * enum and legacy numeric isactive values.
     */
    private function applyActiveStatusFilter(BaseBuilder $builder, string $status): void
    {
        $normalized = strtolower($status);

        if (in_array($normalized, ['enable', 'enabled', 'active', '1', 'true', 'yes', 'on'], true)) {
            $builder->groupStart()
                ->where('isactive', 'Enable')
                ->orWhere('isactive', 'Enabled')
                ->orWhere('isactive', 1)
                ->orWhere('isactive', '1')
                ->groupEnd();

            return;
        }

        if (in_array($normalized, ['disable', 'disabled', 'inactive', '0', 'false', 'no', 'off'], true)) {
            $builder->groupStart()
                ->where('isactive', 'Disabled')
                ->orWhere('isactive', 0)
                ->orWhere('isactive', '0')
                ->groupEnd();
        }
    }

    /** True if the tables needed for family search exist. */
    private function hasFamilySearchTables(): bool
    {
        return $this->db->tableExists('member')
            && $this->db->tableExists('sector');
    }

    /** True if the tables needed for audit search exist. */
    private function hasAuditSearchTables(): bool
    {
        return $this->db->tableExists('audit_trails')
            && $this->db->tableExists('users')
            && $this->db->tableExists('member');
    }

    /** Sector IDs whose name/description match the keyword (so search can match sector text). */
    private function sectorIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        return array_map(
            static fn (array $sector): int => (int) $sector['sectorID'],
            $this->db->table('sector')
                ->select('sectorID')
                ->like('name', $keyword)
                ->orLike('description', $keyword)
                ->get()
                ->getResultArray()
        );
    }

    // Service/program IDs whose name or category matches the keyword (for deep search).
    private function serviceIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('services')) {
            return [];
        }

        return array_map(
            static fn (array $service): int => (int) $service['serviceID'],
            $this->db->table('services')
                ->select('serviceID')
                ->groupStart()
                    ->like('name', $keyword)
                    ->orLike('category', $keyword)
                ->groupEnd()
                ->get()
                ->getResultArray()
        );
    }

    // Member IDs assigned any service matching the keyword (services -> member_services).
    private function memberIdsForServiceKeyword(string $keyword): array
    {
        $serviceIds = $this->serviceIdsForKeyword($keyword);

        if ($serviceIds === [] || ! $this->db->tableExists('member_services')) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['memberID'],
            $this->db->table('member_services')
                ->select('memberID')
                ->whereIn('serviceID', $serviceIds)
                ->get()
                ->getResultArray()
        )));
    }

    // Adds a comma-separated 'service_name' to each deep-search row, reusing the
    // member_services junction (MemberServiceModel) and the services name map (ServiceModel).
    private function withServiceNames(array $rows): array
    {
        if ($rows === [] || ! $this->db->tableExists('member_services')) {
            return $rows;
        }

        $serviceMap = (new \App\Models\Families\MemberServiceModel())
            ->getServiceIdsByMemberIds(array_column($rows, 'memberID'));

        $allServiceIds = [];

        foreach ($serviceMap as $ids) {
            foreach ((array) $ids as $id) {
                $allServiceIds[] = (int) $id;
            }
        }

        $serviceNameMap = (new \App\Models\Lookups\ServiceModel())->getNameMapByIds($allServiceIds);

        foreach ($rows as &$row) {
            $memberId = (int) ($row['memberID'] ?? 0);
            $names = [];

            foreach ((array) ($serviceMap[$memberId] ?? []) as $serviceId) {
                $serviceId = (int) $serviceId;

                if (isset($serviceNameMap[$serviceId])) {
                    $names[] = $serviceNameMap[$serviceId];
                }
            }

            $row['service_name'] = implode(', ', $names);
        }

        return $rows;
    }

    /** Adds username/role and member-name display fields to audit search rows. */
    private function withAuditNames(array $rows): array
    {
        $users = $this->userMap(array_column($rows, 'userID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $userId = (int) ($row['userID'] ?? 0);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];
            // NULL/0 userID is an action with no users row. Failed logins / system
            // errors are real (non-Developer) events shown to admins; everything else
            // with no user is the .env Developer.
            $user = $userId > 0
                ? ($users[$userId] ?? ['username' => '', 'role' => ''])
                : $this->systemAuditActor((string) ($row['user_action'] ?? ''));

            $row['username'] = $user['username'];
            $row['user_role'] = $user['role'];
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
            $row['member_name'] = $this->formatMemberName($memberName);
        }

        return $rows;
    }

    /** {username, role} label for an audit row with no users row, keyed by action. */
    private function systemAuditActor(string $action): array
    {
        return match (strtoupper(trim($action))) {
            'LOGIN_FAILED' => ['username' => 'unknown user', 'role' => 'Login'],
            'SYSTEM_ERROR' => ['username' => 'system', 'role' => 'System'],
            default => ['username' => 'developer', 'role' => 'Developer'],
        };
    }

    /** User IDs whose username matches the keyword (so audit search matches by operator). */
    private function userIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        return array_map(
            static fn (array $user): int => (int) $user['userID'],
            $this->db->table('users')
                ->select('userID')
                ->like('username', $keyword)
                ->get()
                ->getResultArray()
        );
    }

    /** Member IDs whose first/last name matches the keyword (audit search by subject). */
    private function memberIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        return array_map(
            static fn (array $member): int => (int) $member['memberID'],
            $this->db->table('member')
                ->select('memberID')
                ->groupStart()
                ->like('firstname', $keyword)
                ->orLike('lastname', $keyword)
                ->groupEnd()
                ->get()
                ->getResultArray()
        );
    }

    /** Trims a search keyword. */
    private function normalizeKeyword(string $keyword): string
    {
        return trim($keyword);
    }

    /** Returns the date only if it's a valid Y-m-d, else '' (ignored by filters). */
    private function normalizeDate(string $date): string
    {
        $date = trim($date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    }
}
