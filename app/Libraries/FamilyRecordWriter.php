<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Scanner\QrControlModel;

/**
 * Persists one family (head + members + service assignments) and writes the
 * FAMILY_CREATED audit row.
 *
 * This is the single source of truth for the "create a family" write, shared by:
 *   - the manual Add Family form (FamilyController::store), and
 *   - the Excel bulk importer, which now runs in the background job worker
 *     (App\Jobs\FamilyImportJob, via App\Libraries\FamilyExcelImporter).
 *
 * The DB transaction is owned by the CALLER, not this class. Both callers wrap one
 * family per transaction (the worker does so deliberately, so a huge import never
 * holds one giant transaction and a single bad family is isolated). On any failure
 * this throws FamilyRecordWriteException; the caller rolls back and reports it.
 */
class FamilyRecordWriter
{
    public function __construct(
        private MemberModel $memberModel,
        private MemberServiceModel $memberServiceModel,
        private ServiceModel $serviceModel,
        private AuditTrailsModel $auditModel,
        private ?QrControlModel $qrControlModel = null,
    ) {
        $this->qrControlModel ??= new QrControlModel();
    }

    /**
     * Persists a single family. Caller must already be inside a transaction.
     *
     * @param array                                          $headPayload    `member` row for the head (relationship forced to 'Head').
     * @param list<array{payload: array, serviceIds: int[]}> $memberPayloads One entry per additional member: its row + its service IDs.
     * @param int[]                                          $headServiceIds Service IDs to assign to the head.
     * @param int                                            $operatorUserId users.userID of the operator (for the audit row).
     * @param string                                         $auditSuffix    Optional note appended to the audit full_description (e.g. " via Excel import").
     * @param int|null                                       $controlNo      Paper QR control number for the head (from the import's "QR Number" column); null for manual entry.
     *
     * @return int The new head member ID.
     *
     * @throws FamilyRecordWriteException on any insert/assignment failure.
     */
    public function persistFamily(
        array $headPayload,
        array $memberPayloads,
        array $headServiceIds,
        int $operatorUserId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $auditSuffix = '',
        ?int $controlNo = null,
    ): int {
        $headPayload['relationship'] = 'Head';

        $headId = $this->memberModel->createHead($headPayload);

        if ($headId === false) {
            throw new FamilyRecordWriteException('Head of family could not be saved. Please check required fields.');
        }

        if ($controlNo !== null && $controlNo > 0) {
            $this->qrControlModel->assign($controlNo, $headId);
        }

        foreach ($memberPayloads as $entry) {
            $payload = $entry['payload'] ?? [];
            $memberId = $this->memberModel->addFamilyMember($headId, $payload);

            if ($memberId === false) {
                throw new FamilyRecordWriteException('One family member could not be saved.');
            }

            $this->assignServices($memberId, $entry['serviceIds'] ?? [], 'one family member');
        }

        $this->assignServices($headId, $headServiceIds, 'the head of family');

        $this->logCreated(
            $operatorUserId,
            $headId,
            $headPayload,
            count($memberPayloads),
            count($headServiceIds),
            $ipAddress,
            $userAgent,
            $auditSuffix,
        );

        return $headId;
    }

    /**
     * Assigns a list of service IDs to a member, skipping IDs that don't exist
     * (matches the manual form's tolerant behavior). Throws only when an existing
     * service genuinely fails to link.
     *
     * @param int[] $serviceIds
     */
    private function assignServices(int $memberId, array $serviceIds, string $who): void
    {
        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId <= 0 || ! $this->serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($this->memberServiceModel->assignService($memberId, $serviceId) === false) {
                throw new FamilyRecordWriteException('A selected service could not be assigned to ' . $who . '.');
            }
        }
    }

    /**
     * Writes the FAMILY_CREATED audit row (when the audit table exists), mirroring
     * the description the manual form produces.
     */
    private function logCreated(
        int $operatorUserId,
        int $headId,
        array $headPayload,
        int $memberCount,
        int $serviceCount,
        ?string $ipAddress,
        ?string $userAgent,
        string $auditSuffix,
    ): void {
        if (! $this->auditModel->hasTable()) {
            return;
        }

        $headName = trim(trim((string) ($headPayload['firstname'] ?? '')) . ' ' . trim((string) ($headPayload['lastname'] ?? '')));

        $this->auditModel->logAction(
            $operatorUserId,
            $headId,
            'FAMILY_CREATED',
            'Created family profile for ' . $headName . '.',
            $ipAddress,
            $userAgent,
            'Head of family: ' . $headName . '; added ' . $memberCount . ' additional member(s); '
                . $serviceCount . ' service(s) assigned to the head' . $auditSuffix
        );
    }
}
