<?php

namespace App\Libraries;

/**
 * Builds a batch's eligibility roster: the frozen list of family heads a
 * distribution batch covers. One query serves two callers, so the count an
 * admin approves in the batch-open modal is exactly the denominator the batch
 * gets. Called once per batch, never from the dashboard.
 *
 * Eligible means: an active family head, holding a QR control number, whose
 * barangay and sector fall inside the batch's filters. Empty filter arrays mean
 * no restriction on that dimension.
 */
class EligibilityBuilder
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Shared query. Selects distinct family heads matching the filters.
     *
     * @param list<int> $barangayIds empty means citywide
     * @param list<int> $sectorIds   empty means all sectors
     */
    private function scoped(array $barangayIds, array $sectorIds): \CodeIgniter\Database\BaseBuilder
    {
        // qr_control.headID has no unique constraint, so a head could in theory
        // pick up a second control row. select()+distinct() here, not just at
        // the materialize() call site, so count() and materialize() can never
        // diverge on the join: CI4's countAllResults() wraps a DISTINCT select
        // in a subquery and counts that, so both callers count the same rows.
        $b = $this->db->table('member')
            ->select('member.memberID AS headID')
            ->distinct()
            ->join('qr_control', 'qr_control.headID = member.memberID')
            ->where('member.memberID = member.headID', null, false)
            ->where('member.dt_deleted IS NULL', null, false);

        if ($barangayIds !== []) {
            $b->whereIn('member.barangayID', $barangayIds);
        }

        // A head's sectors live in member_sectors (V22). IN (SELECT ...) rather
        // than a join: a head in two of the batch's sectors would otherwise
        // produce two rows, and count() would report more families than exist.
        $sectorIds = array_values(array_filter(array_map('intval', $sectorIds), static fn (int $id): bool => $id > 0));

        if ($sectorIds !== []) {
            $b->where(
                $this->db->prefixTable('member') . '.memberID IN (SELECT memberID FROM '
                    . $this->db->prefixTable('member_sectors')
                    . ' WHERE sectorID IN (' . implode(',', $sectorIds) . '))',
                null,
                false
            );
        }

        return $b;
    }

    /** Preview count for the batch-open modal. */
    public function count(array $barangayIds, array $sectorIds): int
    {
        try {
            return $this->scoped($barangayIds, $sectorIds)
                ->countAllResults();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Freezes the roster for a batch. Returns the number of rows written, which
     * the caller stores as distribution_batch.eligible_count. Clears any prior
     * roster first so an explicit rebuild is idempotent.
     *
     * Returns false, not 0, when the delete or insert genuinely fails - an
     * empty roster and a broken write must stay distinguishable, because the
     * caller (DistributionBatchModel::open()) uses this to decide whether the
     * batch it just inserted is real or has to be discarded.
     */
    public function materialize(int $batchId, array $barangayIds, array $sectorIds): int|false
    {
        if ($batchId <= 0) {
            return 0;
        }

        try {
            // Both writes report failure by returning false rather than
            // throwing, so neither result can be discarded: a delete that
            // failed leaves the previous roster in place, and reporting a row
            // count over it would tell the caller a batch is real when its
            // roster is somebody else's.
            if ($this->db->table('batch_eligibility')->where('batch_id', $batchId)->delete() === false) {
                return false;
            }

            $rows = $this->scoped($barangayIds, $sectorIds)
                ->get()
                ->getResultArray();

            if ($rows === []) {
                return 0;
            }

            $payload = array_map(
                static fn ($r) => ['batch_id' => $batchId, 'headID' => (int) $r['headID']],
                $rows
            );
            if ($this->db->table('batch_eligibility')->insertBatch($payload) === false) {
                return false;
            }

            return count($payload);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
