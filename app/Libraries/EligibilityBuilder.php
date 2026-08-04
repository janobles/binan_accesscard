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
        $b = $this->db->table('member')
            ->join('qr_control', 'qr_control.headID = member.memberID')
            ->where('member.memberID = member.headID', null, false)
            ->where('member.dt_deleted IS NULL', null, false);

        if ($barangayIds !== []) {
            $b->whereIn('member.barangayID', $barangayIds);
        }

        // sectorID stores a JSON array of ints (e.g. "[10]"), matched with
        // JSON_CONTAINS like the rest of the codebase (see SectorIds::containsCondition).
        // Runs once per batch open, so the scan cost is paid once and never on a poll.
        if ($sectorIds !== []) {
            $b->groupStart();
            foreach ($sectorIds as $sectorId) {
                $b->orWhere(SectorIds::containsCondition((int) $sectorId, 'member.sectorID'), null, false);
            }
            $b->groupEnd();
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
     */
    public function materialize(int $batchId, array $barangayIds, array $sectorIds): int
    {
        if ($batchId <= 0) {
            return 0;
        }

        try {
            $this->db->table('batch_eligibility')->where('batch_id', $batchId)->delete();

            $rows = $this->scoped($barangayIds, $sectorIds)
                ->select('member.memberID AS headID')
                ->distinct()
                ->get()
                ->getResultArray();

            if ($rows === []) {
                return 0;
            }

            $payload = array_map(
                static fn ($r) => ['batch_id' => $batchId, 'headID' => (int) $r['headID']],
                $rows
            );
            $this->db->table('batch_eligibility')->insertBatch($payload);

            return count($payload);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
