<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Read-only subsidy-distribution statistics for the Reports tab. Every method is
 * scoped to a distribution batch and returns a safe empty shape on any DB error,
 * matching the scanner module's no-DB test posture. "Eligible" and "served" are
 * both defined against a batch's frozen roster (`batch_eligibility`), not a live
 * guess, so numbers a closed batch already reported never drift afterwards.
 */
class SubsidyStatsModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    private static function pct(int $received, int $total): int
    {
        return $total === 0 ? 0 : (int) round($received / $total * 100);
    }

    /**
     * Headline batch figures, all from indexed counts. The denominator is the
     * batch's frozen roster, not a live query, so a closed batch's coverage
     * never drifts when profiling data changes afterwards.
     *
     * @return array{eligible:int,served:int,remaining:int,coverage:int,voided:int}
     */
    public function coverage(int $batchId): array
    {
        $empty = ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0];
        if ($batchId <= 0) {
            return $empty;
        }

        try {
            $eligible = (int) ($this->db->table('distribution_batch')
                ->select('eligible_count')
                ->where('batch_id', $batchId)
                ->get()->getRowArray()['eligible_count'] ?? 0);

            $served = (int) ($this->db->table('subsidy_distribution')
                ->select('COUNT(DISTINCT memberID) AS n')
                ->where('batch_id', $batchId)
                ->where('dt_voided', null)
                ->get()->getRowArray()['n'] ?? 0);

            $voided = (int) ($this->db->table('subsidy_distribution')
                ->select('COUNT(*) AS n')
                ->where('batch_id', $batchId)
                ->where('dt_voided IS NOT NULL', null, false)
                ->get()->getRowArray()['n'] ?? 0);

            return [
                'eligible'  => $eligible,
                'served'    => $served,
                'remaining' => max(0, $eligible - $served),
                'coverage'  => self::pct($served, $eligible),
                'voided'    => $voided,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Per-barangay progress inside the batch's roster, worst coverage first so
     * the top row is where staff get sent. Groups on the indexed barangayID.
     *
     * @return list<array{barangay:string,total:int,received:int,coverage:int}>
     */
    public function byBarangay(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $rows = $this->db->table('batch_eligibility')
                ->select("COALESCE(barangay.name, 'Unassigned') AS barangay,"
                    . ' COUNT(*) AS total,'
                    . ' COUNT(subsidy_distribution.distribution_id) AS received')
                ->join('member', 'member.memberID = batch_eligibility.headID')
                ->join('barangay', 'barangay.barangayID = member.barangayID', 'left')
                ->join(
                    'subsidy_distribution',
                    'subsidy_distribution.memberID = batch_eligibility.headID'
                        . ' AND subsidy_distribution.batch_id = batch_eligibility.batch_id'
                        . ' AND subsidy_distribution.dt_voided IS NULL',
                    'left'
                )
                ->where('batch_eligibility.batch_id', $batchId)
                ->groupBy('barangay')
                ->get()->getResultArray();

            $out = array_map(static fn ($r) => [
                'barangay' => (string) $r['barangay'],
                'total'    => (int) $r['total'],
                'received' => (int) $r['received'],
                'coverage' => self::pct((int) $r['received'], (int) $r['total']),
            ], $rows);

            usort($out, static fn ($a, $b) => $a['coverage'] <=> $b['coverage']);

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The unclaimed families: on the roster, no live distribution in this batch.
     * This is the liquidation artifact, and the reason the dashboard exists.
     *
     * @return list<array{headID:int,name:string,barangay:string,contact:string}>
     */
    public function remaining(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $rows = $this->db->table('batch_eligibility')
                ->select('batch_eligibility.headID,'
                    . ' member.firstname, member.lastname,'
                    . " COALESCE(barangay.name, 'Unassigned') AS barangay,"
                    . " COALESCE(member.contactnumber, '') AS contact")
                ->join('member', 'member.memberID = batch_eligibility.headID')
                ->join('barangay', 'barangay.barangayID = member.barangayID', 'left')
                ->join(
                    'subsidy_distribution',
                    'subsidy_distribution.memberID = batch_eligibility.headID'
                        . ' AND subsidy_distribution.batch_id = batch_eligibility.batch_id'
                        . ' AND subsidy_distribution.dt_voided IS NULL',
                    'left'
                )
                ->where('batch_eligibility.batch_id', $batchId)
                ->where('subsidy_distribution.distribution_id IS NULL', null, false)
                ->orderBy('barangay', 'ASC')
                ->orderBy('member.lastname', 'ASC')
                ->get()->getResultArray();

            return array_map(static fn ($r) => [
                'headID'   => (int) $r['headID'],
                'name'     => trim((string) $r['firstname'] . ' ' . (string) $r['lastname']),
                'barangay' => (string) $r['barangay'],
                'contact'  => (string) $r['contact'],
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cumulative families served across the batch, 15 minute buckets. A flat
     * tail means scanning has stopped, which is the one thing a live view must
     * surface.
     *
     * @return list<array{label:string,cumulative:int}>
     */
    public function servedTimeline(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $rows = $this->db->table('subsidy_distribution')
                ->select('FLOOR(UNIX_TIMESTAMP(dt_created) / 900) AS bucket,'
                    . ' MIN(dt_created) AS ts,'
                    . ' COUNT(DISTINCT memberID) AS served')
                ->where('batch_id', $batchId)
                ->where('dt_voided', null)
                ->groupBy('bucket')
                ->orderBy('bucket', 'ASC')
                ->get()->getResultArray();

            $running = 0;
            $out     = [];
            foreach ($rows as $r) {
                $running += (int) $r['served'];
                $out[] = [
                    'label'      => date('g:i A', strtotime((string) $r['ts'])),
                    'cumulative' => $running,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Per-scanner performance within one batch: handouts logged and distinct
     * families (control numbers) served, most families first. $onlyUserId
     * narrows to a single user for the scanner-role reports view.
     */
    public function perScanner(int $batchId, ?int $onlyUserId = null): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $b = $this->db->table('subsidy_distribution')
                ->select('subsidy_distribution.userID,'
                    . " COALESCE(users.username, 'Unknown') AS scanner,"
                    . ' COUNT(subsidy_distribution.distribution_id) AS handouts,'
                    . ' COUNT(DISTINCT subsidy_distribution.control_no) AS families')
                ->join('users', 'users.userID = subsidy_distribution.userID', 'left')
                ->where('subsidy_distribution.batch_id', $batchId)
                ->where('subsidy_distribution.dt_voided', null)
                ->groupBy('subsidy_distribution.userID')
                ->orderBy('families', 'DESC')
                ->orderBy('scanner', 'ASC');
            if ($onlyUserId !== null) {
                $b->where('subsidy_distribution.userID', $onlyUserId);
            }

            return array_map(static fn ($r) => [
                'userID'   => (int) $r['userID'],
                'scanner'  => (string) $r['scanner'],
                'handouts' => (int) $r['handouts'],
                'families' => (int) $r['families'],
            ], $b->get()->getResultArray());
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * One kiosk's handouts bucketed into fixed time windows within a batch, for
     * the throughput-over-time chart. Buckets align to $bucketMinutes boundaries;
     * each row is a bucket that actually had activity, ordered oldest first.
     *
     * @return list<array{label:string,families:int,handouts:int}>
     */
    public function timelineForUserInBatch(int $batchId, int $userId, int $bucketMinutes = 15): array
    {
        if ($batchId <= 0 || $userId <= 0) {
            return [];
        }

        $seconds = max(60, $bucketMinutes * 60);

        try {
            $rows = $this->db->table('subsidy_distribution')
                ->select('FLOOR(UNIX_TIMESTAMP(dt_created) / ' . $seconds . ') AS bucket,'
                    . ' MIN(dt_created) AS ts,'
                    . ' COUNT(DISTINCT control_no) AS families,'
                    . ' COUNT(distribution_id) AS handouts')
                ->where('batch_id', $batchId)
                ->where('userID', $userId)
                ->where('dt_voided', null)
                ->groupBy('bucket')
                ->orderBy('bucket', 'ASC')
                ->get()->getResultArray();

            return array_map(static fn ($r) => [
                'label'    => date('g:i A', strtotime((string) $r['ts'])),
                'families' => (int) $r['families'],
                'handouts' => (int) $r['handouts'],
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Per-batch cache key. A closed batch's figures are immutable, so they are
     * cached indefinitely; the open batch caches briefly and is invalidated by
     * every scan, mirroring how AuditTrailsModel clears the dashboard counts.
     */
    public static function cacheKey(int $batchId): string
    {
        return 'subsidy_batch_stats_' . $batchId;
    }

    public function forgetBatch(int $batchId): void
    {
        cache()->delete(self::cacheKey($batchId));
    }

    /**
     * Everything the dashboard's batch zone needs, in one cached payload.
     * $isOpen decides the TTL: closed batches never change again, so they save
     * with ttl 0 (the file cache handler's "never expires").
     *
     * coverage()/byBarangay()/perScanner()/servedTimeline() each swallow
     * \Throwable and return an empty shape rather than raise, per the scanner
     * module's no-DB test posture, so a transient DB error inside them is
     * indistinguishable from "batch genuinely has no data yet" once it reaches
     * this method. A DB health probe runs first, on its own try/catch: if the
     * probe fails, the snapshot still gets returned (each component method
     * degrades to its own empty shape, unchanged) but nothing is written to
     * cache, so a closed batch never freezes on an all-zero read caused by a
     * blip rather than reality.
     */
    public function batchSnapshot(int $batchId, bool $isOpen): array
    {
        $cached = cache(self::cacheKey($batchId));
        if (is_array($cached)) {
            return $cached;
        }

        $snapshot = [
            'coverage'   => $this->coverage($batchId),
            'byBarangay' => $this->byBarangay($batchId),
            'perScanner' => $this->perScanner($batchId),
            'timeline'   => $isOpen ? $this->servedTimeline($batchId) : [],
        ];

        if ($this->dbIsHealthy()) {
            cache()->save(self::cacheKey($batchId), $snapshot, $isOpen ? 10 : 0);
        }

        return $snapshot;
    }

    /**
     * Cheap connectivity probe run after the four stats calls, on batchSnapshot()'s
     * behalf. It only proves the DB is reachable at that moment; it does not
     * inspect what happened during the calls above it, so a connection that
     * drops mid-computation and recovers before this probe would still let a
     * false empty snapshot through. It catches the common case (DB down or
     * still down) without adding a query to every one of the four methods.
     */
    private function dbIsHealthy(): bool
    {
        try {
            $this->db->simpleQuery('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
