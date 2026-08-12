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

    /** Cache key for the Overview tab's four counts. Same 60 second TTL as STATS_CACHE_KEY. */
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
     * The Overview tab's four all-time figures: how many households we know
     * about, how many distributions we have staged, how many households those
     * reached, and how many we have never reached.
     *
     * neverServed is profiled minus ever served, with no QR requirement. A
     * family whose card has not been printed yet has still never been served,
     * and gating on qr_control would make everServed and neverServed add up to
     * the carded total rather than the profiled one, so the Overview cards
     * would not reconcile.
     *
     * everServed joins back to member so it counts active heads only. Without
     * that, a soft-deleted head holding a distribution would count as served
     * while being excluded from families, and neverServed could go negative.
     *
     * @return array{families:int,cardsIssued:int,distributions:int,everServed:int,neverServed:int}
     */
    public function programStats(): array
    {
        $cached = cache(self::PROGRAM_STATS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $families = $this->countFamilies();

        $everServed = 0;
        if ($this->db->tableExists('member') && $this->db->tableExists('subsidy_distribution')) {
            $everServed = (int) $this->db->table('subsidy_distribution sd')
                ->select('COUNT(DISTINCT sd.memberID) AS n')
                ->join('member m', 'm.memberID = sd.memberID')
                ->where('m.memberID = m.headID', null, false)
                ->where('m.dt_deleted IS NULL', null, false)
                ->where('sd.dt_voided', null)
                ->get()->getRowArray()['n'] ?? 0;
        }

        $distributions = $this->db->tableExists('distribution_batch')
            ? $this->db->table('distribution_batch')->countAllResults()
            : 0;

        $stats = [
            'families'      => $families,
            'cardsIssued'   => $this->countCardsIssued(),
            'distributions' => $distributions,
            'everServed'    => $everServed,
            'neverServed'   => max(0, $families - $everServed),
        ];

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

    /**
     * Counts active family heads whose access card has actually been generated.
     *
     * Not the same as counting qr_control rows, which is what this did before
     * V22: that row is written the moment a worker types the paper control
     * number during profiling, so profiling two families reported two cards
     * issued with nothing printed. card_generated_at is stamped by
     * QrCardController once the PDF is built.
     *
     * DISTINCT on the head, because qr_control keeps a row per control number
     * and a reissued card leaves the family with two; the figure sits beside the
     * family count and has to stay comparable to it.
     */
    private function countCardsIssued(): int
    {
        if (! $this->db->tableExists('qr_control') || ! $this->db->tableExists('member')) {
            return 0;
        }

        $builder = $this->db->table('qr_control q')
            ->select('COUNT(DISTINCT q.headID) AS n')
            ->join('member m', 'm.memberID = q.headID')
            ->where('m.memberID = m.headID', null, false)
            ->where('m.dt_deleted IS NULL', null, false);

        if ($this->db->fieldExists('card_generated_at', 'qr_control')) {
            $builder->where('q.card_generated_at IS NOT NULL', null, false);
        }

        return (int) ($builder->get()->getRowArray()['n'] ?? 0);
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
