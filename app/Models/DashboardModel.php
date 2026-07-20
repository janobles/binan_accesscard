<?php

namespace App\Models;

use App\Models\Concerns\ModelQueryHelpers;
use CodeIgniter\Database\BaseConnection;

/**
 * Provides dashboard summary data for controller pages.
 *
 * This model keeps reporting queries out of the UI views and controllers.
 */
class DashboardModel
{
    use ModelQueryHelpers;

    private BaseConnection $db;

    /** Accepts an optional DB connection (defaults to the shared one) for testing. */
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Cache key for the dashboard headline counts. The audit-trail writer
     * deletes this key after every logged mutation, so the 60 second TTL is
     * only a fallback for changes that bypass the app (direct DB edits).
     */
    public const STATS_CACHE_KEY = 'dashboard_stats';

    /**
     * Returns the four headline counts (families, members, active sectors, active
     * services) for the dashboard summary cards. Frontend: dashboard overview.
     *
     * Counts are cached for 60 seconds because they scan the member table and
     * every dashboard visit was recomputing them. See STATS_CACHE_KEY for how
     * the cache stays fresh after mutations.
     */
    public function stats(): array
    {
        $cached = cache(self::STATS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $stats = [
            'families' => $this->countFamilies(),
            'members' => $this->countMembers(),
            // "Active Sectors" / "Services and Programs" cards: count only live
            // rows so archiving lowers the figure and restoring raises it again.
            'sectors' => $this->countActiveLookup('sector'),
            'assistance' => $this->countActiveLookup('services'),
        ];

        cache()->save(self::STATS_CACHE_KEY, $stats, 60);

        return $stats;
    }

    /**
     * Returns the newest family heads with sector names resolved, for the
     * dashboard's recent-families list. (DashboardModel is a lightweight reporting
     * model separate from the Eloquent-style MemberModel.)
     */
    public function recentFamilies(int $limit = 10): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $rows = $this->db->table('member')
            ->select('memberID, firstname, lastname, contactnumber, relationship, headID, sectorID, dt_created')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false)
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    /** Counts active family heads (memberID = headID). */
    private function countFamilies(): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->db->table('member')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    /** Counts all active members (heads + relatives). */
    private function countMembers(): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->db->table('member')
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    /**
     * Count live rows in a lookup table, excluding archived ones (dt_deleted set)
     * when the column exists. Used by the Active Sectors / Services & Programs cards.
     */
    private function countActiveLookup(string $table): int
    {
        if (! $this->db->tableExists($table)) {
            return 0;
        }

        $builder = $this->db->table($table);

        if ($this->db->fieldExists('dt_deleted', $table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->countAllResults();
    }
}
