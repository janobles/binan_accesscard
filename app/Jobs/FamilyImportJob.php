<?php

namespace App\Jobs;

use App\Libraries\FamilyExcelImporter;
use App\Libraries\FamilyRecordWriteException;
use App\Libraries\FamilyRecordWriter;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Scanner\QrControlModel;
use Config\Database;
use Throwable;

/**
 * Background handler for the 'family_import' job type: parses + validates a queued
 * .xlsx (payload['storedPath']) and writes its families ONE PER TRANSACTION,
 * checkpointing every $batch families.
 *
 * That keeps the import off the web request's timeout/memory limit, never holds a
 * single huge transaction, allows partial success (a bad family is isolated and the
 * rest still import), and survives a mid-job crash (the next worker resumes from the
 * job's checkpoint, re-reading the counters/errors already stored in result_json).
 */
class FamilyImportJob implements JobHandlerInterface
{
    /** Families per checkpoint flush. */
    private int $batch = 50;

    public function handle(array $payload, array $job, JobReporter $reporter): JobOutcome
    {
        $path = (string) ($payload['storedPath'] ?? '');

        if ($path === '' || ! is_file($path)) {
            return JobOutcome::failed('The uploaded file is no longer available on the server.');
        }

        $memberModel = new MemberModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            $this->cleanup($path);

            return JobOutcome::failed('The database is missing required tables from accesscardV1.4.sql.');
        }

        $importer = new FamilyExcelImporter();

        try {
            $valid = $importer->process($path);
        } catch (Throwable $e) {
            $this->cleanup($path);

            return JobOutcome::failed('The file could not be read as an .xlsx saved from the template.');
        }

        if (! $valid) {
            $errors = array_values($importer->getErrors());
            $this->cleanup($path);

            return JobOutcome::failed(
                'Nothing was imported — ' . count($errors) . ' validation issue(s). Fix the listed rows and re-upload.',
                ['imported' => 0, 'failed' => 0, 'members' => 0, 'errors' => $errors],
            );
        }

        $families = $importer->getFamilies();
        $total    = count($families);
        $reporter->setTotal($total);

        // Resume support: continue from the checkpoint, re-loading the running
        // counters + error list a crashed run already stored on the job.
        $start  = max(0, (int) ($job['checkpoint'] ?? 0));
        $prior  = $this->decode($job['result_json'] ?? null);
        $done   = (int) ($prior['imported'] ?? 0);
        $failed = (int) ($prior['failed'] ?? 0);
        $skipped = (int) ($prior['skipped'] ?? 0);
        $members = (int) ($prior['members'] ?? 0);
        $errors = (isset($prior['errors']) && is_array($prior['errors'])) ? $prior['errors'] : [];

        $db     = Database::connect();
        $userId = (int) ($job['userID'] ?? 0);
        $ip     = $job['ip_address'] ?? null;
        $ua     = $job['user_agent'] ?? null;

        $writer = new FamilyRecordWriter($memberModel, new MemberServiceModel(), new ServiceModel(), new AuditTrailsModel());
        $qrControlModel = new QrControlModel();

        // By-reference capture so each checkpoint reads the LIVE counters/errors as
        // the loop mutates them (an arrow fn would freeze them at their start values).
        $snapshot = function () use (&$done, &$failed, &$skipped, &$members, &$errors): array {
            return [
                'imported' => $done,
                'failed'   => $failed,
                'skipped'  => $skipped,
                'members'  => $members,
                'errors'   => array_values($errors),
            ];
        };

        $reporter->checkpoint($done + $failed + $skipped, $start, $snapshot());

        for ($i = $start; $i < $total; $i++) {
            $family = $families[$i];
            $head   = $family['headPayload'] ?? [];

            // Skip families that already exist rather than inserting a duplicate. A
            // committed family from earlier in THIS run is on file too, so this also
            // catches duplicates that appear twice within the same upload.
            if ($memberModel->activeHeadExists(
                (string) ($head['firstname'] ?? ''),
                (string) ($head['lastname'] ?? ''),
                $head['birthday'] ?? null,
            )) {
                $skipped++;
                $headName = trim(((string) ($head['firstname'] ?? '')) . ' ' . ((string) ($head['lastname'] ?? '')));
                $errors[] = [
                    'sheetRow' => null,
                    'familyNo' => (string) ($family['familyNo'] ?? ''),
                    'message'  => 'Skipped — a family for ' . ($headName !== '' ? $headName : 'this head') . ' already exists.',
                ];

                if ((($i - $start + 1) % $this->batch) === 0) {
                    $reporter->checkpoint($done + $failed + $skipped, $i + 1, $snapshot());
                    $reporter->pause();
                }

                continue;
            }

            // A QR number already mapped to another family fails fast with a clear
            // message; the qr_control primary key stays as the race-safe backstop.
            $controlNo = (int) ($family['familyNo'] ?? 0);

            if ($controlNo > 0 && $qrControlModel->takenByOtherHead($controlNo, 0)) {
                $failed++;
                $errors[] = [
                    'sheetRow' => null,
                    'familyNo' => (string) ($family['familyNo'] ?? ''),
                    'message'  => 'QR Number ' . $controlNo . ' is already assigned to another family.',
                ];

                if ((($i - $start + 1) % $this->batch) === 0) {
                    $reporter->checkpoint($done + $failed + $skipped, $i + 1, $snapshot());
                    $reporter->pause();
                }

                continue;
            }

            // One family = one transaction: a single bad family is isolated and the
            // rest of the file still imports.
            $db->transBegin();

            try {
                $writer->persistFamily(
                    $family['headPayload'],
                    $family['memberPayloads'],
                    $family['headServiceIds'],
                    $userId,
                    $ip,
                    $ua,
                    ' via Excel import (queued)',
                    $controlNo,
                );

                if ($db->transStatus() === false) {
                    throw new FamilyRecordWriteException('The database rejected the family.');
                }

                $db->transCommit();
                $done++;
                $members += count($family['memberPayloads']);
            } catch (Throwable $e) {
                $db->transRollback();
                $failed++;
                $errors[] = [
                    'sheetRow' => null,
                    'familyNo' => (string) ($family['familyNo'] ?? ''),
                    'message'  => 'Could not save family ' . ($family['familyNo'] ?? '') . ': ' . $e->getMessage(),
                ];
            }

            if ((($i - $start + 1) % $this->batch) === 0) {
                $reporter->checkpoint($done + $failed + $skipped, $i + 1, $snapshot());
                // Breathing room for interactive users between chunks.
                $reporter->pause();
            }
        }

        $reporter->checkpoint($done + $failed + $skipped, $total, $snapshot());
        $this->cleanup($path);

        $skipNote = $skipped > 0 ? ' Skipped ' . $skipped . ' already on file.' : '';

        if ($failed === 0) {
            return JobOutcome::done(
                'Imported ' . $done . ' family record(s) and ' . $members . ' additional member(s).' . $skipNote,
                $snapshot(),
            );
        }

        $message = 'Imported ' . $done . ' of ' . $total . ' family record(s); ' . $failed . ' could not be saved.' . $skipNote;

        return $done > 0
            ? JobOutcome::partial($message, $snapshot())
            : JobOutcome::failed($message, $snapshot());
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function cleanup(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
