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

    /** Cache key for the dashboard's Zone 1 program strip. Same 60 second TTL as STATS_CACHE_KEY. */
    public const PROGRAM_STATS_CACHE_KEY = 'dashboard_program_stats';

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
     * Zone 1 of the dashboard: the quiet program-to-date strip that never moves
     * with the batch selector. "Never served" is the pool the next batch draws
     * from - a family head with a QR but no live (unvoided) distribution in any
     * batch, ever. Cached 60 seconds like stats().
     *
     * @return array{families:int,neverServed:int}
     */
    public function programStats(): array
    {
        $cached = cache(self::PROGRAM_STATS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        if (! $this->db->tableExists('member')) {
            return ['families' => 0, 'neverServed' => 0];
        }

        $neverServed = 0;
        if ($this->db->tableExists('qr_control') && $this->db->tableExists('subsidy_distribution')) {
            $neverServed = (int) $this->db->table('member')
                ->where('memberID = headID', null, false)
                ->where('dt_deleted IS NULL', null, false)
                ->where('EXISTS (SELECT 1 FROM qr_control WHERE qr_control.headID = member.memberID)', null, false)
                ->where(
                    'NOT EXISTS (SELECT 1 FROM subsidy_distribution'
                        . ' WHERE subsidy_distribution.memberID = member.memberID'
                        . ' AND subsidy_distribution.dt_voided IS NULL)',
                    null,
                    false
                )
                ->countAllResults();
        }

        $stats = ['families' => $this->countFamilies(), 'neverServed' => $neverServed];

        cache()->save(self::PROGRAM_STATS_CACHE_KEY, $stats, 60);

        return $stats;
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
