<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\Qr\ControlNumber;
use App\Libraries\Qr\QrImageGenerator;
use App\Libraries\RoleAccess;
use App\Libraries\Scanner\BatchScope;
use App\Libraries\SessionAccount;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Auth\UserModel;
use App\Models\Families\MemberModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\QrControlModel;
use App\Models\Scanner\SubsidyDistributionModel;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner module: resolve a paper QR control number to a family, show its aid
 * history, and log a new subsidy distribution. Scanner/Admin/Developer only.
 *
 * The kiosk (scan + performance) renders in the kiosk shell
 * (Scanner/kiosk-layout): no sidebar/topbar, one slim header with the live
 * personal counter. The subsidy type comes from the active distribution batch
 * (set by an admin when the batch is opened).
 *
 * - scan():        GET  scanner/scan        -> one-action scan UI; empty state when no batch is open.
 * - history():     GET  scanner/history/{controlNo} -> "View all" page: History tab (server
 *                                               search/filter/pagination) + Family Information
 *                                               tab (client-side, families are small).
 * - performance(): GET  scanner/performance  -> this kiosk's own live metrics.
 * - stats():       GET  scanner/stats        -> JSON own-performance snapshot for polling.
 * - logAid():      POST scanner/log          -> the whole scan in one call: resolve family,
 *                                               per-batch duplicate guard, insert + audit;
 *                                               409 when no open batch.
 */
class ScanController extends BaseController
{
    /** GET scanner/scan - kiosk lookup UI. Subsidy type comes from the active batch. */
    public function scan(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);

        return view('Scanner/scan', [
            'pageTitle'    => 'Scan',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $activeBatch,
            'subsidyType'  => $activeBatch !== null
                ? [
                    'subsidy_type_id' => (int) $activeBatch['subsidy_type_id'],
                    'name'            => (string) ($activeBatch['subsidy_type_name'] ?? 'Subsidy'),
                ]
                : null,
            'myBatchCount' => $activeBatch !== null
                ? model(SubsidyDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id'])
                : 0,
            'scheduleBanner' => $this->scheduleBanner($activeBatch),
            // Printed so a person in the room can catch a laptop whose clock is
            // wrong. The database shares that clock, so nothing in code can.
            'systemNow'      => date('D, F j, Y g:i A'),
        ]);
    }

    /**
     * Plain-language state for the scanner screen: what is running or what is
     * next, where, and for how long. Replaces the bare "no open batch"
     * refusal, which told a person at a venue nothing they could act on.
     *
     * @return array{state:string,lines:list<string>}
     */
    private function scheduleBanner(?array $activeBatch): array
    {
        $today = date('Y-m-d');

        if ($activeBatch !== null) {
            $start = (string) ($activeBatch['scheduled_start'] ?? $today);
            $end   = (string) ($activeBatch['scheduled_end'] ?? $today);
            $day   = (int) ((strtotime($today) - strtotime($start)) / 86400) + 1;
            $total = (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;

            return [
                'state' => 'open',
                'lines' => [
                    (string) $activeBatch['name'] . ($activeBatch['venue'] !== '' ? ', ' . (string) $activeBatch['venue'] : ''),
                    'Day ' . $day . ' of ' . $total . ', until ' . date('g:i A', strtotime((string) $activeBatch['daily_end_time'])) . '.',
                ],
            ];
        }

        // Starts from today, not tomorrow: a batch plotted for today that has
        // not opened yet (before its daily_start_time, or before a reconcile
        // has run) is still the one worth naming, not "nothing plotted ahead".
        //
        // A batch is dropped from the list only once it can no longer run
        // again: its span has passed, or it has both started and closed with
        // no scheduled day left. closed_at alone is not enough, because a
        // multi-day batch grace-closes between days and reopens on the next
        // one still inside its span (BatchScheduleWindow::verdict()).
        $finished = null;
        $upcoming = [];

        foreach (model(DistributionBatchModel::class)->scheduledBetween($today, date('Y-m-d', strtotime('+1 year'))) as $batch) {
            $end = (string) $batch['scheduled_end'];
            if ($end < $today) {
                continue;
            }

            if ($end === $today && $batch['started_at'] !== null && $batch['closed_at'] !== null) {
                $finished ??= $batch;

                continue;
            }

            $upcoming[] = $batch;
        }

        $next  = $upcoming[0] ?? null;
        $lines = [];

        if ($finished !== null) {
            // Named rather than skipped: staff who just closed the batch need
            // to see that the kiosk agrees, not a line that reads as if the
            // day were still ahead of them.
            $lines[] = 'Today\'s distribution has ended.';
            $lines[] = 'Closed: ' . (string) $finished['name']
                . ($finished['venue'] !== '' ? ', ' . (string) $finished['venue'] : '') . '.';
        }

        if ($next === null) {
            if ($lines === []) {
                $lines[] = 'Nothing scheduled today, and nothing plotted ahead.';
            }

            return ['state' => 'idle', 'lines' => $lines];
        }

        $venuePart = $next['venue'] !== '' ? ', ' . (string) $next['venue'] : '';
        // A batch that starts today is still the one to name, unless today's
        // batch has already finished: then it is simply what comes next.
        $startsToday = $finished === null && (string) $next['scheduled_start'] === $today;

        if ($lines === []) {
            $lines[] = $startsToday ? 'Scheduled today, not open yet.' : 'Nothing scheduled today.';
        }

        $lines[] = ($startsToday ? 'Opens: ' : 'Next: ') . (string) $next['name'] . $venuePart . ', '
            . date('D M j', strtotime((string) $next['scheduled_start'])) . ', '
            . date('g:i A', strtotime((string) $next['daily_start_time'])) . '.';

        return ['state' => 'idle', 'lines' => $lines];
    }

    /**
     * GET scanner/history/{controlNo} - full, filterable subsidy history for
     * one family, reached from the scan panel's "View all" link. Read-only:
     * no batch, no scan action. Two tabs: History (every past scan of this
     * control number - server search/filter/pagination) and Family
     * Information (the head + rest of the family - client-side search/filter,
     * families are small so no pagination).
     */
    public function history(int $controlNo): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $members = new MemberModel();
        $headId  = model(QrControlModel::class)->headForControl($controlNo);
        $head    = $headId !== null ? $members->findHead($headId) : null;
        if ($head === null) {
            return redirect()->to('scanner/scan')->with('error', 'QR control number is not registered.');
        }

        // Audit Logs tab: scoped to this control number only - scan a
        // different one and it's a different set. No search/filters, not
        // paginated - one control number's scan instances are a small set.
        $distModel   = model(SubsidyDistributionModel::class);
        $historyRows = $distModel->historyAllFor($controlNo, '', 0, 0);

        // Family Information tab: head + rest of the family, sectors and
        // services/programs kept as two separate lists (not merged).
        $memberRows = array_values(array_filter(
            $members->familyMembers($headId),
            static fn (array $m): bool => (int) $m['memberID'] !== $headId
        ));
        $splitIds = array_map(static fn (array $m): int => (int) $m['memberID'], array_merge([$head], $memberRows));
        $split    = $members->referenceBadgesSplit($splitIds);

        foreach ($memberRows as $i => $m) {
            $memberRows[$i]['sectors']          = $split[(int) $m['memberID']]['sectors'] ?? [];
            $memberRows[$i]['servicesPrograms']  = $split[(int) $m['memberID']]['servicesPrograms'] ?? [];
        }

        $viewData = [
            'pageTitle'    => 'Subsidy History',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'controlNo'    => $controlNo,
            'headName'     => trim(($head['firstname'] ?? '') . ' ' . ($head['lastname'] ?? '')),
            // Audit Logs tab
            'historyRows'  => $historyRows,
            // Family Information tab
            'head'         => [
                'firstname'     => (string) ($head['firstname'] ?? ''),
                'lastname'      => (string) ($head['lastname'] ?? ''),
                'suffix'        => (string) ($head['suffix'] ?? ''),
                'sex'           => (string) ($head['sex'] ?? ''),
                'birthday'      => (string) ($head['birthday'] ?? ''),
                'civilstatus'   => (string) ($head['civilstatus'] ?? ''),
                'contactnumber' => (string) ($head['contactnumber'] ?? ''),
                'job'           => (string) ($head['job'] ?? ''),
                'address'       => (string) ($head['address'] ?? ''),
                'sectors'         => $split[(int) $head['memberID']]['sectors'] ?? [],
                'servicesPrograms' => $split[(int) $head['memberID']]['servicesPrograms'] ?? [],
            ],
            'members'      => $memberRows,
            'allSectorOptions'         => array_map(static fn (array $s): string => (string) ($s['shortcode'] ?? ''), (new SectorModel())->getSectorOptions()),
            'allServiceProgramOptions' => $this->allServiceProgramOptions(),
        ];

        // The scan panel injects this inline (no page reload) via fetch() with
        // X-Requested-With: XMLHttpRequest - same fragment the full page uses,
        // just without the simple-layout shell and "Back to Scan" header.
        if ($this->request->isAJAX()) {
            return view('Scanner/history-fragment', $viewData);
        }

        return view('Scanner/history', $viewData);
    }

    /**
     * Every service category name + every service shortcode, system-wide -
     * so the "View all" page's Services/Programs filter offers every option
     * that could ever appear, not just what this family happens to have.
     *
     * @return list<string>
     */
    private function allServiceProgramOptions(): array
    {
        $services   = (new ServiceModel())->getActive();
        $categories = array_map(static fn (array $s): string => trim((string) ($s['category'] ?? '')), $services);
        $shortcodes = array_map(static fn (array $s): string => trim((string) ($s['shortcode'] ?? '')), $services);

        return array_values(array_unique(array_filter(array_merge($categories, $shortcodes))));
    }

    /** GET scanner/performance - this kiosk's own live metrics. */
    public function performance(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $batchModel = model(DistributionBatchModel::class);
        $active     = $batchModel->activeBatch();
        $batches    = $batchModel->allBatches();
        [$batchId, $batchRow] = BatchScope::resolve($batches, $active, (int) $this->request->getGet('batch'));

        $userId       = $this->resolveViewedScanner();
        $viewingOther = $userId !== (int) (session('user_id') ?? 0);
        $snapshot     = $this->kioskSnapshot($batchId, $userId, $batchRow);

        return view('Scanner/performance', array_merge([
            'pageTitle'    => $viewingOther ? 'Station Performance' : 'My Performance',
            // The topbar account menu names whoever is signed in, which is the
            // admin when one has drilled into a station. The figures below
            // belong to someone else, so the heading names them separately.
            'username'     => session('username') ?? 'Scanner',
            'stationName'  => $viewingOther
                ? $this->accountUsername($userId)
                : (string) (session('username') ?? 'Scanner'),
            'viewingOther' => $viewingOther,
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $active,
            'batches'      => $batches,
            'batchId'      => $batchId,
            'batchOpen'    => $batchRow !== null && ($batchRow['closed_at'] ?? null) === null,
            // Carried into the batch selector and the stats poll so an admin
            // drilled into a station stays on it, see resolveViewedScanner().
            'viewedScannerId' => $viewingOther ? $userId : null,
        ], $snapshot));
    }

    /**
     * Which scanner's figures the performance page is showing.
     *
     * A Scanner sees their own session and nothing else. An Admin or Developer
     * may pass ?scanner=<userID> to open one station from the dashboard's
     * Stations grid, and only when that account is genuinely a scanner. Without
     * this the page would answer with the viewer's own (usually zero) numbers
     * under the clicked station's name, which is a silent wrong answer.
     */
    private function resolveViewedScanner(): int
    {
        $sessionUserId = (int) (session('user_id') ?? 0);
        // ?scanner[]=1 hands back an array, and casting one to int is a warning
        // that becomes a 500 under `development`.
        $requestedRaw  = $this->request->getGet('scanner');
        $requested     = is_scalar($requestedRaw) ? (int) $requestedRaw : 0;

        if ($requested <= 0 || $requested === $sessionUserId) {
            return $sessionUserId;
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        if (! in_array($role, ['Admin', 'Developer'], true)) {
            return $sessionUserId;
        }

        return model(UserModel::class)->hasAccountLevel($requested, 'scanner')
            ? $requested
            : $sessionUserId;
    }

    /** The account username for a userID, or 'Scanner' when the row is gone. */
    private function accountUsername(int $userId): string
    {
        return model(UserModel::class)->usernameById($userId) ?? 'Scanner';
    }

    /**
     * One kiosk's live figures for a batch: totals, a time-bucketed throughput
     * timeline, and derived pace (families/hour, busiest window). Shared by the
     * performance page and the polling endpoint so both stay in sync.
     *
     * @return array{mine:array{families:int,handouts:int},timeline:list<array{label:string,families:int,handouts:int}>,pace:array{perHour:int,busiest:string}}
     */
    private function kioskSnapshot(int $batchId, int $userId, ?array $batchRow): array
    {
        $stats    = model(SubsidyStatsModel::class);
        $mineRow  = $batchId > 0 ? ($stats->perScanner($batchId, $userId)[0] ?? null) : null;
        $mine     = ['families' => (int) ($mineRow['families'] ?? 0), 'handouts' => (int) ($mineRow['handouts'] ?? 0)];
        $timeline = $batchId > 0 ? $stats->timelineForUserInBatch($batchId, $userId) : [];

        // Pace uses the batch's active span (open batch → now, else its close time).
        $perHour = 0;
        if ($mine['families'] > 0 && $batchRow !== null && ! empty($batchRow['started_at'])) {
            $start   = strtotime((string) $batchRow['started_at']);
            $end     = ! empty($batchRow['closed_at']) ? strtotime((string) $batchRow['closed_at']) : time();
            $hours   = max(0.05, ($end - $start) / 3600);
            $perHour = (int) round($mine['families'] / $hours);
        }

        $busiest = '';
        $peak    = -1;
        foreach ($timeline as $bucket) {
            if ($bucket['families'] > $peak) {
                $peak    = $bucket['families'];
                $busiest = $bucket['label'];
            }
        }

        return [
            'mine'     => $mine,
            'timeline' => $timeline,
            'pace'     => ['perHour' => $perHour, 'busiest' => $busiest],
        ];
    }

    /** GET scanner/stats - JSON own-performance snapshot for kiosk polling. */
    public function stats(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $batchModel = model(DistributionBatchModel::class);
        $userId     = $this->resolveViewedScanner();

        // The performance page can be pinned to one batch, and an admin opening
        // a station from the dashboard usually pins it to a closed one. Without
        // honouring ?batch= the poll answers for whatever batch happens to be
        // open and overwrites the figures the page rendered. No ?batch= keeps
        // the kiosk's own behaviour: the open batch, or nothing.
        $requested   = $this->request->getGet('batch');
        $requestedId = is_scalar($requested) ? max(0, (int) $requested) : 0;

        $batch = $requestedId > 0
            ? BatchScope::resolve($batchModel->allBatches(), null, $requestedId)[1]
            : $batchModel->activeBatch();

        if ($batch === null) {
            return $this->response->setJSON(['batch' => null, 'families' => 0, 'handouts' => 0]);
        }

        $batchId  = (int) $batch['batch_id'];
        $snapshot = $this->kioskSnapshot($batchId, $userId, $batch);

        return $this->response->setJSON([
            'batch'    => [
                'id'       => $batchId,
                'name'     => (string) $batch['name'],
                'subsidy_type' => (string) ($batch['subsidy_type_name'] ?? ''),
            ],
            'families' => $snapshot['mine']['families'],
            'handouts' => $snapshot['mine']['handouts'],
            'timeline' => $snapshot['timeline'],
            'pace'     => $snapshot['pace'],
            'updated'  => date('c'),
        ]);
    }

    /**
     * Resolves a control number to the family panel payload: the head
     * (identity, address/barangay, sector-service badges) and the rest of the
     * family, badges attached, head excluded (the panel shows the head
     * separately). Returns null when the control number isn't registered or
     * its head row is missing.
     */
    private function resolveFamily(int $controlNo): ?array
    {
        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return null;
        }

        $members = new MemberModel();
        $head    = $members->findHead($headId);
        if ($head === null) {
            log_message('error', 'Scanner lookup: control {c} maps to missing head {h}', ['c' => $controlNo, 'h' => $headId]);
            return null;
        }

        $memberRows = array_values(array_filter(
            $members->familyMembers($headId),
            static fn (array $m): bool => (int) $m['memberID'] !== $headId
        ));

        $badgeIds = array_map(static fn (array $m): int => (int) $m['memberID'], array_merge([$head], $memberRows));
        $badges   = $members->referenceBadges($badgeIds);
        $barangay = (string) ($head['barangay'] ?? '');

        foreach ($memberRows as $i => $m) {
            $memberRows[$i]['badges'] = $badges[(int) $m['memberID']] ?? [];
        }

        return [
            'control_no'    => $controlNo,
            'qr_code_image' => (new QrImageGenerator())->dataUri(ControlNumber::payload($controlNo)),
            'head' => [
                'memberID'      => (int) $head['memberID'],
                'firstname'     => (string) ($head['firstname'] ?? ''),
                'lastname'      => (string) ($head['lastname'] ?? ''),
                'suffix'        => (string) ($head['suffix'] ?? ''),
                'sex'           => (string) ($head['sex'] ?? ''),
                'birthday'      => (string) ($head['birthday'] ?? ''),
                'contactnumber' => (string) ($head['contactnumber'] ?? ''),
                'address'       => (string) ($head['address'] ?? ''),
                'barangay'      => $barangay,
                'badges'        => $badges[(int) $head['memberID']] ?? [],
            ],
            'members' => $memberRows,
        ];
    }

    /**
     * POST scanner/log - the whole scan in one action. Resolves the control
     * number to a family, refuses when the family was already logged in the
     * open batch (Duplicate Entry), otherwise inserts a distribution for the
     * family HEAD dated today and audits it. The response always carries the
     * family panel data so the kiosk renders in one round trip.
     */
    public function logAid(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        if (! $this->validate(['control_no' => 'required|is_natural_no_zero'])) {
            return $this->response->setStatusCode(422)
                ->setJSON(['errors' => $this->validator->getErrors()]);
        }
        $controlNo = (int) $this->request->getPost('control_no');

        $familyPayload = $this->resolveFamily($controlNo);
        if ($familyPayload === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'QR control number is not registered.']);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['error' => 'No active distribution batch. Ask an administrator to open one.']);
        }
        $batchId = (int) $activeBatch['batch_id'];
        $userId  = (int) (session('user_id') ?? 0);
        $headId  = $familyPayload['head']['memberID'];

        $familyPayload['subsidy_type_name'] = (string) ($activeBatch['subsidy_type_name'] ?? 'Subsidy');

        // One handout per family per batch: a repeat scan reports the original
        // entry instead of logging again. The check is server-side so a stale
        // kiosk page can never double-log.
        $existing = model(SubsidyDistributionModel::class)->inBatch($controlNo, $batchId);
        if ($existing !== null) {
            return $this->response->setJSON($familyPayload + [
                'ok'           => true,
                'logged'       => false,
                'duplicate'    => $existing,
                'history'      => model(SubsidyDistributionModel::class)->historyFor($controlNo),
                'myBatchCount' => model(SubsidyDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
            ]);
        }

        $subsidyTypeId = (int) $activeBatch['subsidy_type_id'];
        $claimDate = date('Y-m-d');

        // The insert and its audit row must land together: without a shared
        // transaction, a handout could get logged with no audit trail (or an
        // audit row could survive a rolled-back handout).
        $db = db_connect();
        $db->transStart();

        $distributionId = model(SubsidyDistributionModel::class)->logAid([
            'control_no'  => $controlNo,
            'memberID'        => (int) $headId,
            'subsidy_type_id' => $subsidyTypeId,
            'claim_date'      => $claimDate,
            'userID'      => $userId,
            'batch_id'    => $batchId,
        ]);

        $audited = $distributionId > 0 && (new AuditTrailsModel())->logAction(
            $userId,
            $headId,
            'Logged subsidy distribution',
            'Control #' . $controlNo,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            'Subsidy type ID ' . $subsidyTypeId . ' on ' . $claimDate
        );

        if (! $audited) {
            $db->transRollback();

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the subsidy distribution.']);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the subsidy distribution.']);
        }

        model(SubsidyStatsModel::class)->forgetBatch($batchId);

        return $this->response->setJSON($familyPayload + [
            'ok'           => true,
            'logged'       => true,
            'duplicate'    => null,
            'history'      => model(SubsidyDistributionModel::class)->historyFor($controlNo),
            'myBatchCount' => model(SubsidyDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
        ]);
    }
}
