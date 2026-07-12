<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Read-only aid-distribution statistics for the Reports tab. Every method is
 * scoped to an optional distribution batch only, and returns a safe empty shape
 * on any DB error, matching the scanner module's no-DB test posture.
 * "Received" is defined at the family (head) level: a family counts as having
 * received aid when any scan under its control_no produced a distribution row.
 */
class AidStatsModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** Applies the optional batch scope to aid_distribution. */
    private function applyScope($builder, ?int $batchId)
    {
        if ($batchId !== null && $batchId > 0) {
            $builder->where('aid_distribution.batch_id', $batchId);
        }

        return $builder;
    }

    private static function pct(int $received, int $total): int
    {
        return $total === 0 ? 0 : (int) round($received / $total * 100);
    }

    /** Family-level received vs not-yet counts + coverage percent. */
    public function receivedVsNot(?int $batchId = null): array
    {
        try {
            $total = (int) $this->db->table('qr_control')
                ->select('headID')
                ->distinct()
                ->countAllResults();

            $b = $this->db->table('qr_control')
                ->select('qr_control.headID')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no')
                ->groupBy('qr_control.headID');
            $this->applyScope($b, $batchId);
            $received = count($b->get()->getResultArray());

            return [
                'total'       => $total,
                'received'    => $received,
                'notReceived' => max(0, $total - $received),
                'coverage'    => self::pct($received, $total),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'received' => 0, 'notReceived' => 0, 'coverage' => 0];
        }
    }

    /** Per-barangay family totals + received + coverage. */
    public function byBarangay(?int $batchId = null): array
    {
        try {
            $barangayExpr = $this->db->fieldExists('barangay', 'member')
                ? "COALESCE(NULLIF(TRIM(member.barangay), ''), 'Unspecified')"
                : "COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(member.address, ',', -1)), ''), 'Unspecified')";

            // Total families per barangay (head's barangay).
            $totals = $this->db->table('qr_control')
                ->select($barangayExpr . ' AS barangay,'
                    . ' COUNT(DISTINCT qr_control.headID) AS total')
                ->join('member', 'member.memberID = qr_control.headID', 'left')
                ->groupBy('barangay')
                ->get()->getResultArray();

            // Received families per barangay, within the batch scope.
            $rb = $this->db->table('qr_control')
                ->select($barangayExpr . ' AS barangay,'
                    . ' COUNT(DISTINCT qr_control.headID) AS received')
                ->join('member', 'member.memberID = qr_control.headID', 'left')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no');
            $this->applyScope($rb, $batchId);
            $recv = [];
            foreach ($rb->groupBy('barangay')->get()->getResultArray() as $r) {
                $recv[$r['barangay']] = (int) $r['received'];
            }

            $out = [];
            foreach ($totals as $t) {
                $total    = (int) $t['total'];
                $received = $recv[$t['barangay']] ?? 0;
                $out[] = [
                    'barangay' => $t['barangay'],
                    'total'    => $total,
                    'received' => $received,
                    'coverage' => self::pct($received, $total),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Handout counts per service, within the batch scope, busiest first.
     * Services with zero handouts are omitted (the catalogue is ~46 rows).
     */
    public function byService(?int $batchId = null): array
    {
        try {
            $b = $this->db->table('services')
                ->select('services.name AS service, services.shortcode AS service_code,'
                    . ' COUNT(aid_distribution.aidID) AS count')
                ->join('aid_distribution', 'aid_distribution.service_id = services.serviceID', 'left');
            $this->applyScope($b, $batchId);
            $rows = $b->groupBy('services.serviceID')
                ->having('count >', 0)
                ->orderBy('count', 'DESC')
                ->orderBy('services.name', 'ASC')
                ->get()->getResultArray();

            return array_map(static fn ($r) => [
                'service'      => (string) $r['service'],
                'service_code' => (string) ($r['service_code'] ?? ''),
                'count'        => (int) $r['count'],
            ], $rows);
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
            $b = $this->db->table('aid_distribution')
                ->select('aid_distribution.userID,'
                    . " COALESCE(users.username, 'Unknown') AS scanner,"
                    . ' COUNT(aid_distribution.aidID) AS handouts,'
                    . ' COUNT(DISTINCT aid_distribution.control_no) AS families')
                ->join('users', 'users.userID = aid_distribution.userID', 'left')
                ->where('aid_distribution.batch_id', $batchId)
                ->groupBy('aid_distribution.userID')
                ->orderBy('families', 'DESC')
                ->orderBy('scanner', 'ASC');
            if ($onlyUserId !== null) {
                $b->where('aid_distribution.userID', $onlyUserId);
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
            $rows = $this->db->table('aid_distribution')
                ->select('FLOOR(UNIX_TIMESTAMP(dt_created) / ' . $seconds . ') AS bucket,'
                    . ' MIN(dt_created) AS ts,'
                    . ' COUNT(DISTINCT control_no) AS families,'
                    . ' COUNT(aidID) AS handouts')
                ->where('batch_id', $batchId)
                ->where('userID', $userId)
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
}
