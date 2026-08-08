<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Distribution batches: one row per giving event, plotted on the Schedule tab
 * of the distribution page and spanning one or more days at a venue.
 *
 * scheduled_start and scheduled_end are the plan; started_at and closed_at are
 * what actually happened, written by reconcileSchedule() rather than by a
 * human. At most one batch may be open (closed_at IS NULL) at a time, which is
 * why overlapping date spans are refused at save time. All methods keep the
 * scanner module's no-DB test posture: safe empty shapes on any DB error.
 */
class DistributionBatchModel extends Model
{
    /** The label colours a batch may carry. Hex values live in theme.css. */
    public const COLORS = ['green', 'yellow', 'orange', 'red', 'purple', 'blue'];

    protected $table         = 'distribution_batch';
    protected $primaryKey    = 'batch_id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name', 'venue', 'subsidy_type_id', 'scheduled_start', 'scheduled_end',
        'daily_start_time', 'daily_end_time', 'color', 'started_at', 'closed_at',
        'created_by', 'eligible_count',
    ];
    protected $useTimestamps = false;

    /** The single open batch, or null when none (or on DB error). */
    public function activeBatch(): ?array
    {
        try {
            $row = $this->select('distribution_batch.*, subsidy.name AS subsidy_type_name')
                ->join('subsidy', 'subsidy.subsidy_type_id = distribution_batch.subsidy_type_id', 'left')
                ->where('distribution_batch.closed_at', null)
                // Raw and unqualified, because ->where('started_at !=', null)
                // builds "started_at != NULL", which matches nothing, and a
                // table-qualified raw string skips the query builder's prefix
                // handling. A batch scheduled ahead sits with both columns null
                // until reconcileSchedule() opens it, and must not read as
                // active before its scheduled day arrives.
                ->where('started_at IS NOT NULL', null, false)
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Creates or updates a plotted schedule. Never starts the batch: opening is
     * reconcileSchedule()'s job, and the roster freezes then so a batch plotted
     * weeks out covers the families who exist on the day it runs.
     *
     * Returns the batch id, or 0 when the payload is refused: blank name, no
     * subsidy type, an end before its start, dates already taken by another
     * batch, or an edit against a batch that has already started or has scans
     * recorded against it. Callers distinguish the overlap case by calling
     * overlapping() first.
     *
     * @param array{batch_id:int,name:string,venue:string,subsidy_type_id:int,scheduled_start:string,scheduled_end:string,daily_start_time:string,daily_end_time:string,color:string,barangay_ids:list<int>,sector_ids:list<int>} $data
     */
    public function saveSchedule(array $data, int $userId): int
    {
        $name    = trim((string) ($data['name'] ?? ''));
        $start   = (string) ($data['scheduled_start'] ?? '');
        $end     = (string) ($data['scheduled_end'] ?? '');
        $typeId  = (int) ($data['subsidy_type_id'] ?? 0);
        $batchId = (int) ($data['batch_id'] ?? 0);

        if ($name === '' || $typeId <= 0 || $start === '' || $end === '' || $end < $start) {
            return 0;
        }
        if ($this->overlapping($start, $end, $batchId) !== null) {
            return 0;
        }

        $color = (string) ($data['color'] ?? 'green');
        if (! in_array($color, self::COLORS, true)) {
            $color = 'green';
        }

        $row = [
            'name'             => $name,
            'venue'            => trim((string) ($data['venue'] ?? '')),
            'subsidy_type_id'  => $typeId,
            'scheduled_start'  => $start,
            'scheduled_end'    => $end,
            'daily_start_time' => (string) ($data['daily_start_time'] ?? '08:00:00'),
            'daily_end_time'   => (string) ($data['daily_end_time'] ?? '17:00:00'),
            'color'            => $color,
        ];

        try {
            $this->db->transStart();

            if ($batchId > 0) {
                $existing = $this->find($batchId);
                if ($existing === null) {
                    $this->db->transComplete();

                    return 0;
                }
                // Stricter than deleteSchedule(), which only checks
                // hasDistributions(): rewriting scheduled_start/_end or
                // subsidy_type_id here would move a closed batch's span onto
                // today (reopening it, see openDue()) or change what logged
                // distributions are reported under, so a started batch is
                // refused even with no scans against it yet.
                if ($existing['started_at'] !== null || $this->hasDistributions($batchId)) {
                    $this->db->transComplete();

                    return 0;
                }
                if ($this->update($batchId, $row) === false) {
                    $this->db->transComplete();

                    return 0;
                }
            } else {
                $row['created_by'] = $userId > 0 ? $userId : null;
                if ($this->insert($row) === false) {
                    $this->db->transComplete();

                    return 0;
                }
                $batchId = (int) $this->getInsertID();
            }

            // Filters are rewritten wholesale on every save; a partial update
            // would leave a barangay attached that the form no longer lists.
            $this->db->table('batch_barangay')->where('batch_id', $batchId)->delete();
            $this->db->table('batch_sector')->where('batch_id', $batchId)->delete();

            foreach ((array) ($data['barangay_ids'] ?? []) as $id) {
                $this->db->table('batch_barangay')->insert(['batch_id' => $batchId, 'barangayID' => (int) $id]);
            }
            foreach ((array) ($data['sector_ids'] ?? []) as $id) {
                $this->db->table('batch_sector')->insert(['batch_id' => $batchId, 'sectorID' => (int) $id]);
            }

            $this->db->transComplete();

            return $this->db->transStatus() === false ? 0 : $batchId;
        } catch (\Throwable $e) {
            // An exception between transStart() and transComplete() leaves the
            // transaction open on the shared connection and every later query
            // silently joins it. See rebuildRoster()'s catch.
            $this->db->transRollback();

            return 0;
        }
    }

    /**
     * Brings batch state in line with the clock: opens the batch whose plotted
     * span contains today, closes one whose day has finished. Safe to call on
     * every request and a no-op when nothing is due, which is what lets a
     * request filter own the trigger instead of a scheduled task on a laptop
     * that travels to the venue.
     *
     * @param string|null $now 'Y-m-d H:i:s'. Tests pass it; production does not.
     */
    public function reconcileSchedule(?string $now = null): void
    {
        $now ??= date('Y-m-d H:i:s');
        $today = substr($now, 0, 10);

        try {
            // The one batch whose span contains today, or the newest still-open
            // one whose span has passed. Overlaps are refused at save time, so
            // there is never more than one of the first kind.
            $row = $this->groupStart()
                    ->where('scheduled_start <=', $today)
                    ->where('scheduled_end >=', $today)
                ->groupEnd()
                ->orGroupStart()
                    ->where('scheduled_end <', $today)
                    ->where('closed_at', null)
                    // Raw, because ->where('started_at !=', null) builds
                    // "started_at != NULL", which matches nothing.
                    ->where('started_at IS NOT NULL', null, false)
                ->groupEnd()
                ->orderBy('scheduled_start', 'DESC')
                ->first();

            if (! is_array($row)) {
                return;
            }

            $batchId = (int) $row['batch_id'];
            $verdict = \App\Libraries\BatchScheduleWindow::verdict(
                $row,
                $this->lastScanAt($batchId),
                $now
            );

            if ($verdict['action'] === 'open') {
                $this->openDue($row, $now);

                return;
            }

            if ($verdict['action'] === 'close') {
                $this->update($batchId, ['closed_at' => $verdict['closed_at']]);
                (new SubsidyStatsModel())->forgetBatch($batchId);
                $this->auditLifecycle('Closed distribution batch "' . (string) $row['name'] . '" #' . $batchId . ' on schedule', 'Recorded end: ' . (string) $verdict['closed_at']);
            }
        } catch (\Throwable $e) {
            // Reconcile is best effort on a page request. A DB hiccup must not
            // take the page down; the next request tries again and computes the
            // same answer.
        }
    }

    /**
     * Applies an open verdict: stamps started_at and freezes the roster the
     * first time, clears closed_at on a later day or after a premature grace
     * close. The roster never rebuilds on reopen, because a running batch's
     * coverage figure must not drift under it.
     */
    private function openDue(array $row, string $now): void
    {
        $batchId = (int) $row['batch_id'];

        if (($row['started_at'] ?? null) === null) {
            $this->update($batchId, ['started_at' => $now, 'closed_at' => null]);

            $venue    = (string) ($row['venue'] ?? '');
            $eligible = $this->freezeRoster($batchId);

            // Opening is itself the lifecycle transition, and it already
            // happened (started_at is committed above); a roster-freeze
            // failure must not cost the batch its audit row, or this becomes
            // the one open with no trace.
            if ($eligible === false) {
                log_message('error', 'Distribution batch #{id} opened on schedule but its roster freeze failed.', ['id' => $batchId]);
                $this->auditLifecycle(
                    'Opened distribution batch "' . (string) $row['name'] . '" #' . $batchId . ' on schedule',
                    'Roster freeze failed, eligible count not recorded; venue: ' . $venue
                );

                return;
            }

            $this->auditLifecycle(
                'Opened distribution batch "' . (string) $row['name'] . '" #' . $batchId . ' on schedule',
                'Eligible families: ' . $eligible . '; venue: ' . $venue
            );

            return;
        }

        $this->update($batchId, ['closed_at' => null]);
        (new SubsidyStatsModel())->forgetBatch($batchId);
        $this->auditLifecycle('Reopened distribution batch "' . (string) $row['name'] . '" #' . $batchId, 'Still inside its scheduled days.');
    }

    /** Audit row for an automatic transition. User id 0 reads as the system. */
    private function auditLifecycle(string $action, ?string $detail = null): void
    {
        try {
            (new \App\Models\Audit\AuditTrailsModel())->logAction(0, null, $action, $detail);
        } catch (\Throwable $e) {
            // A missing audit row must not roll back a lifecycle transition
            // that already happened.
        }
    }

    /**
     * Freezes the batch's eligibility roster and records the size. Returns the
     * count, or false on a write failure, matching EligibilityBuilder so a
     * caller can tell a failed write from an empty roster. Protected (not
     * private) so a test can force the failure path openDue() guards against.
     */
    protected function freezeRoster(int $batchId): int|false
    {
        $filters  = $this->filtersFor($batchId);
        $eligible = (new \App\Libraries\EligibilityBuilder($this->db))
            ->materialize($batchId, $filters['barangays'], $filters['sectors']);

        if ($eligible === false) {
            return false;
        }

        $this->update($batchId, ['eligible_count' => $eligible]);

        return $eligible;
    }

    /**
     * Defensive cleanup after a roster-write failure. The transaction has
     * already been rolled back by the time this runs, so the batch row and its
     * junction rows should already be gone; this is a second, best-effort pass
     * in case any of them survived, so the caller never leaves an orphan
     * distribution_batch row behind. Failures here are swallowed - the caller is
     * returning 0 regardless.
     */
    private function discardOrphan(int $batchId): void
    {
        try {
            $this->db->table('batch_eligibility')->where('batch_id', $batchId)->delete();
            $this->db->table('batch_barangay')->where('batch_id', $batchId)->delete();
            $this->db->table('batch_sector')->where('batch_id', $batchId)->delete();
            $this->delete($batchId);
        } catch (\Throwable $e) {
            // best effort; nothing more to do
        }
    }

    /**
     * The batch whose plotted dates collide with the given span, or null when
     * the span is free. Two batches may not share a day because at most one
     * batch is ever open and the scanner depends on that.
     */
    public function overlapping(string $start, string $end, int $exceptId = 0): ?array
    {
        if ($start === '' || $end === '') {
            return null;
        }

        try {
            $builder = $this->where('scheduled_start <=', $end)
                ->where('scheduled_end >=', $start);

            if ($exceptId > 0) {
                $builder->where('batch_id !=', $exceptId);
            }

            $row = $builder->orderBy('scheduled_start', 'ASC')->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Batches whose plotted span touches the given date range, for the calendar feed. */
    public function scheduledBetween(string $from, string $to): array
    {
        try {
            return $this->select('distribution_batch.*, subsidy.name AS subsidy_type_name')
                ->join('subsidy', 'subsidy.subsidy_type_id = distribution_batch.subsidy_type_id', 'left')
                ->where('distribution_batch.scheduled_start <=', $to)
                ->where('distribution_batch.scheduled_end >=', $from)
                ->orderBy('distribution_batch.scheduled_start', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * When the newest un-voided scan in a batch landed, or null when none has.
     * Drives the closing anchor, so a voided row must not hold the batch open.
     */
    public function lastScanAt(int $batchId): ?string
    {
        if ($batchId <= 0) {
            return null;
        }

        try {
            $row = $this->db->table('subsidy_distribution')
                ->selectMax('dt_created', 'last_scan')
                ->where('batch_id', $batchId)
                ->where('dt_voided', null)
                ->get()
                ->getRowArray();

            $last = $row['last_scan'] ?? null;

            return is_string($last) && $last !== '' ? $last : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Whether any scan, voided or not, was ever recorded against this batch. */
    public function hasDistributions(int $batchId): bool
    {
        if ($batchId <= 0) {
            return false;
        }

        try {
            return $this->db->table('subsidy_distribution')
                ->where('batch_id', $batchId)
                ->countAllResults() > 0;
        } catch (\Throwable $e) {
            // Refuse the destructive path rather than guess when the count is
            // unavailable.
            return true;
        }
    }

    /**
     * Removes a plotted batch and its filters. Refuses once any scan has been
     * recorded against it, because deleting then would orphan real records.
     */
    public function deleteSchedule(int $batchId): bool
    {
        if ($batchId <= 0 || $this->hasDistributions($batchId)) {
            return false;
        }

        try {
            $this->db->table('batch_eligibility')->where('batch_id', $batchId)->delete();
            $this->db->table('batch_barangay')->where('batch_id', $batchId)->delete();
            $this->db->table('batch_sector')->where('batch_id', $batchId)->delete();

            return $this->delete($batchId) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * A batch's stored filters. Empty arrays mean the batch was citywide or
     * covered all sectors.
     *
     * @return array{barangays:list<int>,sectors:list<int>}
     */
    public function filtersFor(int $batchId): array
    {
        $empty = ['barangays' => [], 'sectors' => []];
        if ($batchId <= 0) {
            return $empty;
        }

        try {
            if ($this->find($batchId) === null) {
                return $empty;
            }

            $brgy = $this->db->table('batch_barangay')
                ->select('barangayID')->where('batch_id', $batchId)->get()->getResultArray();
            $sect = $this->db->table('batch_sector')
                ->select('sectorID')->where('batch_id', $batchId)->get()->getResultArray();

            return [
                'barangays' => array_map(static fn ($r) => (int) $r['barangayID'], $brgy),
                'sectors'   => array_map(static fn ($r) => (int) $r['sectorID'], $sect),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Re-runs the roster for a batch whose profiling data changed after it
     * opened. Deliberately explicit: the roster never moves on its own, because
     * a closed batch's coverage is a printed figure that must not drift. Returns
     * the new eligible count.
     */
    public function rebuildRoster(int $batchId): int
    {
        if ($batchId <= 0) {
            return 0;
        }

        try {
            if ($this->find($batchId) === null) {
                return 0;
            }

            $this->db->transStart();

            $filters  = $this->filtersFor($batchId);
            $eligible = (new \App\Libraries\EligibilityBuilder($this->db))
                ->materialize($batchId, $filters['barangays'], $filters['sectors']);

            // CI4's BaseConnection::query() rolls back and, since transStrict is
            // off by default, resets transStatus() back to true the moment a
            // query fails inside a transaction, so by the time control gets
            // back here transStatus() already reads as success even though the
            // roster write failed. materialize() returning false (not 0) is how
            // it flags a genuine write failure rather than a legitimately empty
            // roster; treat that as fatal directly instead of falling through
            // to the transStatus() check below, which cannot see it.
            if ($eligible === false) {
                $this->db->transComplete();

                return 0;
            }

            $this->update($batchId, ['eligible_count' => $eligible]);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return 0;
            }

            // A closed batch's snapshot is cached with no expiry, so without
            // this the dashboard keeps serving the pre-rebuild coverage
            // forever. Scans invalidate the open batch the same way.
            (new SubsidyStatsModel())->forgetBatch($batchId);

            return $eligible;
        } catch (\Throwable $e) {
            // An exception between transStart() and transComplete() leaves the
            // transaction open on the shared connection, and every query after
            // it in the request silently becomes part of it. Roll back rather
            // than complete: transStatus() can still read true here, which
            // would commit whatever the exception interrupted.
            $this->db->transRollback();

            return 0;
        }
    }

    /** Closes (resets) an open batch by stamping closed_at. */
    public function close(int $batchId): bool
    {
        if ($batchId <= 0) {
            return false;
        }

        try {
            $row = $this->find($batchId);
            if (! is_array($row) || $row['closed_at'] !== null) {
                return false;
            }

            return $this->update($batchId, ['closed_at' => date('Y-m-d H:i:s')]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Every batch, newest first, for the manage table and reports selector. */
    public function allBatches(): array
    {
        try {
            return $this->select('distribution_batch.*, subsidy.name AS subsidy_type_name')
                ->join('subsidy', 'subsidy.subsidy_type_id = distribution_batch.subsidy_type_id', 'left')
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
