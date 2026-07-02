<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Read-only aid-distribution statistics for the Reports tab. Every method is
 * scoped to an optional [from, to] claim_date window and returns a safe empty
 * shape on any DB error, matching the scanner module's no-DB test posture.
 * "Received" is defined at the family (head) level: a family counts as having
 * received aid when any scan under its control_no produced a distribution row.
 */
class AidStatsModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** Applies the optional date window to aid_distribution.claim_date. */
    private function applyRange($builder, ?string $from, ?string $to)
    {
        if ($from !== null && $from !== '') {
            $builder->where('aid_distribution.claim_date >=', $from . ' 00:00:00');
        }
        if ($to !== null && $to !== '') {
            $builder->where('aid_distribution.claim_date <=', $to . ' 23:59:59');
        }

        return $builder;
    }

    private static function pct(int $received, int $total): int
    {
        return $total === 0 ? 0 : (int) round($received / $total * 100);
    }

    /** Family-level received vs not-yet counts + coverage percent. */
    public function receivedVsNot(?string $from = null, ?string $to = null): array
    {
        try {
            $total = (int) $this->db->table('qr_control')
                ->countAllResults();

            $b = $this->db->table('qr_control')
                ->select('qr_control.headID')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no')
                ->groupBy('qr_control.headID');
            $this->applyRange($b, $from, $to);
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
    public function byBarangay(?string $from = null, ?string $to = null): array
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

            // Received families per barangay, within the date window.
            $rb = $this->db->table('qr_control')
                ->select($barangayExpr . ' AS barangay,'
                    . ' COUNT(DISTINCT qr_control.headID) AS received')
                ->join('member', 'member.memberID = qr_control.headID', 'left')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no');
            $this->applyRange($rb, $from, $to);
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

    /** Handout counts per aid type, within the date window, busiest first. */
    public function byAidType(?string $from = null, ?string $to = null): array
    {
        try {
            $b = $this->db->table('aid_type')
                ->select('aid_type.name AS aid_type, COUNT(aid_distribution.aidID) AS count')
                ->join('aid_distribution', 'aid_distribution.aid_type_id = aid_type.aid_type_id', 'left');
            $this->applyRange($b, $from, $to);
            $rows = $b->groupBy('aid_type.aid_type_id')
                ->orderBy('count', 'DESC')
                ->orderBy('aid_type.name', 'ASC')
                ->get()->getResultArray();

            return array_map(static fn ($r) => [
                'aid_type' => (string) $r['aid_type'],
                'count'    => (int) $r['count'],
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
