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
     * never drifts when profiling data changes afterwards. served/voided are
     * joined against batch_eligibility, so a scan for a family outside the
     * roster is still logged and audited but never moves this count; served
     * can therefore never exceed eligible.
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
                ->join(
                    'batch_eligibility',
                    'batch_eligibility.batch_id = subsidy_distribution.batch_id'
                        . ' AND batch_eligibility.headID = subsidy_distribution.memberID'
                )
                ->where('subsidy_distribution.batch_id', $batchId)
                ->where('subsidy_distribution.dt_voided', null)
                ->get()->getRowArray()['n'] ?? 0);

            $voided = (int) ($this->db->table('subsidy_distribution')
                ->select('COUNT(*) AS n')
                ->join(
                    'batch_eligibility',
                    'batch_eligibility.batch_id = subsidy_distribution.batch_id'
                        . ' AND batch_eligibility.headID = subsidy_distribution.memberID'
                )
                ->where('subsidy_distribution.batch_id', $batchId)
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
     * Served counts for every batch at once, keyed by batch_id, for the
     * Overview tab's distributions table.
     *
     * Joined to batch_eligibility on the same terms as coverage(), so a scan
     * for a family outside the roster is invisible here exactly as it is
     * there. Without the join the Overview table would report a larger served
     * figure than the Distribution tab does for the same batch.
     *
     * @return array<int,int>
     */
    public function servedByBatch(): array
    {
        try {
            $rows = $this->db->table('subsidy_distribution sd')
                ->select('sd.batch_id, COUNT(DISTINCT sd.memberID) AS served')
                ->join(
                    'batch_eligibility be',
                    'be.batch_id = sd.batch_id AND be.headID = sd.memberID'
                )
                ->where('sd.dt_voided', null)
                ->groupBy('sd.batch_id')
                ->get()->getResultArray();

            $out = [];
            foreach ($rows as $row) {
                $out[(int) $row['batch_id']] = (int) $row['served'];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Families served per calendar day inside one batch, oldest first, for the
     * rollout chart and the Busiest day card.
     *
     * claim_date is already a date column and is written server-side at scan
     * time, so it needs no DATE() wrapper and cannot be skewed by client input.
     *
     * Joined to batch_eligibility on the same terms as coverage(). That is what
     * makes the per-day figures sum to the Served card: an off-roster or voided
     * scan is invisible to both.
     *
     * One hole in that invariant, left as is. The scan-time duplicate guard in
     * SubsidyDistributionModel::inBatch() keys on control_no, while these counts
     * and the Served card key on memberID, and qr_control.headID carries no
     * unique constraint. A head holding two control numbers, scanned once on
     * each of two days, is one served family but two rows on two days, so the
     * day bars would sum to one more than the Served card. Fixing it belongs in
     * the schema, not here.
     *
     * @return list<array{date:string,label:string,served:int}>
     */
    public function servedByDay(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $rows = $this->db->table('subsidy_distribution sd')
                ->select('sd.claim_date AS day, COUNT(DISTINCT sd.memberID) AS served')
                ->join(
                    'batch_eligibility be',
                    'be.batch_id = sd.batch_id AND be.headID = sd.memberID'
                )
                ->where('sd.batch_id', $batchId)
                ->where('sd.dt_voided', null)
                ->groupBy('sd.claim_date')
                ->orderBy('sd.claim_date', 'ASC')
                ->get()->getResultArray();

            $out = [];
            foreach ($rows as $index => $row) {
                $out[] = [
                    'date'   => (string) $row['day'],
                    'label'  => 'Day ' . ($index + 1),
                    'served' => (int) $row['served'],
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Per-barangay progress inside the batch's roster, best coverage first so
     * the table reads as a leaderboard. Groups on the indexed barangayID.
     *
     * Sorting is not operational here. Distribution happens at one central
     * site and families travel to it, so no one is dispatched to a barangay
     * and there is no worst-first decision to optimise for.
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
                // DISTINCT on both counts: the left join below fans a roster row
                // out once per distribution, so a family that collected twice in
                // one batch (two handouts, one family) counted twice as received
                // and twice again as total. Coverage is families, not handouts.
                ->select("COALESCE(barangay.name, 'Unassigned') AS barangay,"
                    . ' COUNT(DISTINCT batch_eligibility.headID) AS total,'
                    . ' COUNT(DISTINCT subsidy_distribution.memberID) AS received')
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

            usort($out, static fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The shared filter behind every "unclaimed families" read: on the roster,
     * no live distribution in this batch. remaining(), remainingPage() and
     * remainingCount() all build off this one join so the dashboard's paginated
     * tab, its total count, and the PDF's complete list can never disagree
     * about who counts as unclaimed.
     *
     * $keyword, when given, narrows to families whose head name or barangay
     * matches (the Remaining tab's search box). like()/orLike() add the SQL
     * ESCAPE clause but do not themselves escape % or _ inside the match
     * string, so a literal % or _ typed by staff is escaped by hand here
     * (with the query builder's own escape character) before the wildcard %
     * gets wrapped around it - otherwise it would be read as a SQL wildcard
     * instead of the literal character staff typed. Values still reach the
     * database through like()'s own bound placeholder, so quotes need no
     * separate escaping here.
     */
    private function remainingBuilder(int $batchId, ?string $keyword = null): \CodeIgniter\Database\BaseBuilder
    {
        $builder = $this->db->table('batch_eligibility')
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
            ->where('subsidy_distribution.distribution_id', null);

        if ($keyword !== null && $keyword !== '') {
            $escapeChar = $this->db->likeEscapeChar;
            $escaped    = str_replace(
                [$escapeChar, '%', '_'],
                [$escapeChar . $escapeChar, $escapeChar . '%', $escapeChar . '_'],
                $keyword
            );
            $builder->groupStart()
                ->like('member.firstname', $escaped)
                ->orLike('member.lastname', $escaped)
                ->orLike('barangay.name', $escaped)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * The unclaimed families: on the roster, no live distribution in this batch.
     * This is the liquidation artifact the PDF report prints, so it always
     * returns the complete list - never call this for the dashboard's Remaining
     * tab at scale; use remainingPage()/remainingCount() there instead.
     *
     * @return list<array{headID:int,name:string,barangay:string,contact:string}>
     */
    public function remaining(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $rows = $this->remainingBuilder($batchId)
                ->select('batch_eligibility.headID,'
                    . ' member.firstname, member.lastname,'
                    . " COALESCE(barangay.name, 'Unassigned') AS barangay,"
                    . " COALESCE(member.contactnumber, '') AS contact")
                ->orderBy('barangay', 'ASC')
                ->orderBy('member.lastname', 'ASC')
                ->orderBy('batch_eligibility.headID', 'ASC')
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
     * Count behind remainingPage()'s pagination footer. Same filter (and the
     * same optional search keyword) as remainingPage(), so the "Showing X to Y
     * of Z" text can never disagree with the rows the page actually returns.
     */
    public function remainingCount(int $batchId, ?string $keyword = null): int
    {
        if ($batchId <= 0) {
            return 0;
        }

        try {
            return (int) $this->remainingBuilder($batchId, $keyword)
                ->countAllResults();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * One page of remaining(), for the dashboard's Remaining tab. Against the
     * 100k-family target, remaining() lists everyone in one response; this is
     * the paginated substitute for the tab (the PDF's unclaimed list must stay
     * whole, so it keeps calling remaining() directly). $keyword narrows to
     * name/barangay matches, same as remainingCount()'s.
     *
     * Ordered by barangay, then surname, then headID: the first two alone are
     * not unique (two roster families can share both), and LIMIT/OFFSET over
     * an undefined tie order can repeat or skip a row across two pages.
     * headID (the batch_eligibility roster's own key) closes that gap.
     *
     * @return list<array{headID:int,name:string,barangay:string,contact:string}>
     */
    public function remainingPage(int $batchId, int $limit, int $offset, ?string $keyword = null): array
    {
        if ($batchId <= 0 || $limit <= 0) {
            return [];
        }

        try {
            $rows = $this->remainingBuilder($batchId, $keyword)
                ->select('batch_eligibility.headID,'
                    . ' member.firstname, member.lastname,'
                    . " COALESCE(barangay.name, 'Unassigned') AS barangay,"
                    . " COALESCE(member.contactnumber, '') AS contact")
                ->orderBy('barangay', 'ASC')
                ->orderBy('member.lastname', 'ASC')
                ->orderBy('batch_eligibility.headID', 'ASC')
                ->limit($limit, max(0, $offset))
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
     * The date() format a timeline's labels should use, decided once for the
     * whole series so one axis never mixes two formats.
     *
     * Buckets are ordered by time, but a bare clock repeats every day: a batch
     * running Tuesday to Thursday produced an axis reading 9:36 AM, 8:41 AM,
     * 9:22 AM, which looks like it runs backwards. A single day is the live
     * monitoring case and the date there is only noise, so it keeps the clock.
     *
     * @param list<string> $timestamps bucket timestamps, any strtotime format
     */
    public static function timelineLabelFormat(array $timestamps): string
    {
        $days = [];
        foreach ($timestamps as $ts) {
            $time = strtotime((string) $ts);
            if ($time !== false) {
                $days[date('Y-m-d', $time)] = true;
            }
        }

        return count($days) > 1 ? 'M j, g:i A' : 'g:i A';
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

            $format = self::timelineLabelFormat(array_column($rows, 'ts'));

            $running = 0;
            $out     = [];
            foreach ($rows as $r) {
                $running += (int) $r['served'];
                $out[] = [
                    'label'      => date($format, strtotime((string) $r['ts'])),
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

            $format = self::timelineLabelFormat(array_column($rows, 'ts'));

            return array_map(static fn ($r) => [
                'label'    => date($format, strtotime((string) $r['ts'])),
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
        $fingerprint = $this->batchFingerprint($batchId);

        $cached = cache(self::cacheKey($batchId));
        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint && isset($cached['snapshot'])) {
            return $cached['snapshot'];
        }

        $snapshot = [
            'coverage'   => $this->coverage($batchId),
            'byBarangay' => $this->byBarangay($batchId),
            'perScanner' => $this->perScanner($batchId),
            'timeline'   => $isOpen ? $this->servedTimeline($batchId) : [],
            'byDay'      => $this->servedByDay($batchId),
        ];

        if ($fingerprint !== '' && $this->dbIsHealthy()) {
            cache()->save(
                self::cacheKey($batchId),
                ['fingerprint' => $fingerprint, 'snapshot' => $snapshot],
                $isOpen ? 10 : 0
            );
        }

        return $snapshot;
    }

    /**
     * Identity of the batch row behind $batchId, so a cached snapshot can be
     * matched to the batch it was computed from. The cache key is the batch id
     * alone, and a closed batch saves with ttl 0, so without this check any
     * reuse of an id by a different batch (a database reimport, a restore into
     * a fresh schema) serves the old batch's figures forever. The columns are
     * the ones fixed when the batch is created or opened, not the counters that
     * move during distribution, so a live batch keeps its cache hits.
     *
     * @return string Empty when the row is missing or the query fails, which
     *                tells batchSnapshot() to skip the cache write entirely.
     */
    private function batchFingerprint(int $batchId): string
    {
        if ($batchId <= 0) {
            return '';
        }

        try {
            $row = $this->db->table('distribution_batch')
                ->select('name, scheduled_start, started_at, closed_at, created_by')
                ->where('batch_id', $batchId)
                ->get()->getRowArray();
        } catch (\Throwable $e) {
            return '';
        }

        if ($row === null) {
            return '';
        }

        return md5(implode('|', array_map(static fn ($v) => (string) $v, $row)));
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
            // simpleQuery() reports a failed query by returning false rather
            // than throwing, so the catch below is not the only outcome to
            // check: without this the probe called a dead connection healthy.
            return $this->db->simpleQuery('SELECT 1') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
