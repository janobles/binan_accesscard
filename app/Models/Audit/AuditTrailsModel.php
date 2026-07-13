<?php

namespace App\Models\Audit;

use App\Models\Concerns\ModelQueryHelpers;
use CodeIgniter\Model;

/**
 * Records and retrieves audit trail entries for staff actions.
 *
 * Append-only (WORM at the app level): this model only ever inserts and selects.
 * There is intentionally no update or delete path for audit rows, and no caller
 * should add one — an audit trail must not be editable or erasable from the app.
 *
 * Each row carries two narratives: `description` is a short one-line summary, and
 * `full_description` is the composed who/what/when/where detail (see
 * composeFullDescription()).
 */
class AuditTrailsModel extends Model
{
    use ModelQueryHelpers;

    protected $table = 'audit_trails';
    protected $primaryKey = 'auditID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_action',
        'description',
        'full_description',
        'ip_address',
        'user_agent',
        'userID',
        'memberID',
    ];
    protected $useTimestamps = false;

    /** Action types authored without a real users row that are still security-relevant
     *  and therefore shown to admins (unlike Developer rows, which stay hidden). */
    private const SYSTEM_ACTIONS = ['LOGIN_FAILED', 'SYSTEM_ERROR'];

    /** True if the `audit_trails` table exists; callers no-op auditing otherwise. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * Inserts one audit row capturing who did what, plus client IP/user agent.
     * Called across controllers (login/logout, account, family, sector, service
     * actions). $memberId is the affected member or null for staff-only actions;
     * tolerates schemas where memberID is required or user_agent is absent.
     */
    public function logAction(
        int $userId,
        ?int $memberId,
        string $action,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $detail = null
    ): bool {
        // Every mutation in this app logs an audit row, so this is the one
        // choke point where the dashboard counts can change. Drop the cached
        // stats here so the tiles recount on the next dashboard visit.
        cache()->delete(\App\Models\DashboardModel::STATS_CACHE_KEY);

        $memberId = $this->memberIdValue($memberId);

        if ($memberId === null && ! $this->isMemberIdNullable()) {
            log_message('error', 'Audit trail skipped: audit_trails.memberID is required but no affected member was supplied.');

            return false;
        }

        $payload = [
            // Unknown actors use NULL so they do not violate the users FK. Legacy
            // file-backed Developer rows also used NULL (see getRecent / withNames).
            'userID' => $userId > 0 ? $userId : null,
            'memberID' => $memberId,
            'user_action' => $action,
            'description' => $description,
            // Clean labeled-line narrative carrying all six facets — What / Who /
            // When / Where (IP) / Device (UA) — built from the same data logAction
            // already has. Rendered as-is in the per-row Details modal.
            'full_description' => $this->composeFullDescription(
                $userId,
                $action,
                $description,
                $detail,
                $ipAddress,
                $userAgent
            ),
            'ip_address' => $ipAddress,
        ];

        if ($userAgent !== null && $userAgent !== '') {
            if ($this->db->fieldExists('user_agent', $this->table)) {
                $payload['user_agent'] = $userAgent;
            } else {
                $payload['description'] = $this->appendUserAgent($description, $userAgent);
            }
        }

        return $this->insert($payload) !== false;
    }

    /**
     * Returns the latest audit entries (newest first) with user and member names
     * resolved. Developer audit rows (NULL userID) are excluded unless
     * $includeDeveloper is true, so only the Developer sees its own activity.
     * Frontend: the admin Audit Trails page via DashboardPageBuilder.
     */
    public function getRecent(int $limit = 50, bool $includeDeveloper = false): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->select('audit_trails.*')
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit);

        if (! $includeDeveloper) {
            // Hide only the Developer's own rows. Security events authored without a
            // users row (failed logins, system errors) still surface to admins.
            $builder->where(
                "(audit_trails.userID IS NOT NULL OR audit_trails.user_action IN ("
                    . $this->systemActionsInList() . "))",
                null,
                false
            );
        }

        return $this->withNames($builder->findAll());
    }

    /**
     * Returns one user's recent audit entries (newest first) with names resolved.
     * Frontend: the employee Activity page.
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->where('userID', $userId)
            ->orderBy('dt_created', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->withNames($rows);
    }

    /** Fallback when there's no user_agent column: folds the UA into the description. */
    private function appendUserAgent(?string $description, string $userAgent): string
    {
        $base = $description ?? '';
        $suffix = 'UA: ' . $userAgent;

        return $base === '' ? $suffix : $base . ' | ' . $suffix;
    }

    /**
     * Builds the labeled-line narrative stored in `full_description` for a new row:
     * composes the "What" clause (action + summary + optional $detail) then wraps it
     * with Who/When/Where/Device via assembleNarrative(). $detail (e.g. members
     * added, "Role changed from X to Y") enriches the What clause when supplied.
     */
    private function composeFullDescription(
        int $userId,
        string $action,
        ?string $description,
        ?string $detail,
        ?string $ipAddress,
        ?string $userAgent
    ): string {
        return $this->assembleNarrative(
            $this->composeWhat($action, $description, $detail),
            $userId,
            $action,
            $ipAddress,
            $userAgent,
            date('Y-m-d H:i:s')
        );
    }

    /** "What happened" clause: action + short summary + optional caller detail. */
    private function composeWhat(string $action, ?string $description, ?string $detail): string
    {
        $what = trim($action);
        $summary = trim((string) $description);

        if ($summary !== '') {
            $what .= ' — ' . $summary;
        }

        if ($detail !== null && trim($detail) !== '') {
            $what .= ' — ' . trim($detail);
        }

        return $what;
    }

    /**
     * Assembles the six-facet, labeled-line narrative shown in the Details modal.
     * Public so the one-time backfill command can rebuild historical rows from their
     * columns using the exact same format. Lines are newline-joined; the modal box
     * renders them with `white-space: pre-wrap`.
     */
    public function assembleNarrative(
        string $what,
        int $userId,
        string $action,
        ?string $ipAddress,
        ?string $userAgent,
        string $when
    ): string {
        $ip = trim((string) $ipAddress);
        $ua = trim((string) $userAgent);

        return implode("\n", [
            'What: ' . trim($what),
            'Who: ' . $this->actorNarrative($userId, $action),
            'When: ' . $when,
            'Where: ' . ($ip === '' ? 'unknown' : $ip),
            'Device: ' . ($ua === '' ? 'unknown' : $ua),
        ]);
    }

    /**
     * "Who did it" label for the narrative. In a web request, prefers the live
     * session actor when it matches $userId (free, no query); otherwise (and always
     * in CLI) falls back to a users lookup, then to the no-users-row cases
     * (Developer / failed login / system error) resolved by action.
     */
    private function actorNarrative(int $userId, string $action): string
    {
        if ($userId <= 0) {
            $label = $this->systemActorLabel($action);
            $role = trim((string) $label['role']);

            return $role === '' ? $label['username'] : $label['username'] . ' (' . $role . ')';
        }

        if (! is_cli()) {
            $session = session();

            if ($session !== null && (int) $session->get('user_id') === $userId) {
                $username = trim((string) $session->get('username'));
                $role = trim((string) $session->get('role'));
                $role = \App\Libraries\RoleAccess::auditRoleLabel($role) ?? $role;

                if ($username !== '') {
                    return $role === '' ? $username . ' (#' . $userId . ')'
                        : $username . ' (' . $role . ', #' . $userId . ')';
                }
            }
        }

        $user = $this->userMap([$userId])[$userId] ?? null;

        if ($user === null) {
            return 'user #' . $userId;
        }

        // Normalize the raw account_level enum to the audit-facing label (Admin/Encoder/…)
        // so the narrative matches what the session-actor path stores.
        $role = trim((string) ($user['role'] ?? ''));
        $role = \App\Libraries\RoleAccess::auditRoleLabel($role) ?? $role;

        return $role === ''
            ? trim((string) $user['username']) . ' (#' . $userId . ')'
            : trim((string) $user['username']) . ' (' . $role . ', #' . $userId . ')';
    }

    /** {username, role} label for a row with no users row, keyed by its action. */
    private function systemActorLabel(string $action): array
    {
        return match (strtoupper(trim($action))) {
            'LOGIN_FAILED' => ['username' => 'unknown user', 'role' => 'Login'],
            'SYSTEM_ERROR' => ['username' => 'system', 'role' => 'System'],
            default => ['username' => 'developer', 'role' => 'Developer'],
        };
    }

    /** Escaped, comma-joined SYSTEM_ACTIONS list for raw SQL IN() clauses. */
    private function systemActionsInList(): string
    {
        return implode(',', array_map(
            fn (string $action): string => $this->db->escape($action),
            self::SYSTEM_ACTIONS
        ));
    }

    /** Returns the member ID only if it's a real existing member, else null. */
    private function memberIdValue(?int $memberId): ?int
    {
        if ($memberId !== null && $memberId > 0 && $this->memberExists($memberId)) {
            return $memberId;
        }

        return null;
    }

    /** Whether the memberID column allows NULL, so staff-only actions are valid. */
    private function isMemberIdNullable(): bool
    {
        foreach ($this->db->getFieldData($this->table) as $field) {
            if ($field->name === 'memberID') {
                return (bool) ($field->nullable ?? false);
            }
        }

        return true;
    }

    /**
     * Adds display fields (username, user_role, member name) to audit rows via
     * batched user/member lookups (avoids N+1 queries).
     */
    private function withNames(array $rows): array
    {
        $users = $this->userMap(array_column($rows, 'userID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $userId = (int) ($row['userID'] ?? 0);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];
            // A NULL/0 userID is an action with no users row. Failed logins and system
            // errors are system events; everything else is legacy Developer activity.
            $user = $userId > 0
                ? ($users[$userId] ?? ['username' => '', 'role' => ''])
                : $this->systemActorLabel((string) ($row['user_action'] ?? ''));

            $row['username'] = $user['username'];
            $row['user_role'] = $user['role'];
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
            $row['member_name'] = $this->formatMemberName($memberName);
        }

        return $rows;
    }

    /** True if a member row with this ID exists; gates the memberID foreign key. */
    private function memberExists(int $memberId): bool
    {
        if (! $this->db->tableExists('member')) {
            return false;
        }

        return $this->db->table('member')
            ->where('memberID', $memberId)
            ->countAllResults() > 0;
    }

}
