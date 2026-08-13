<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberSectorModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\SectorModel;
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
        private ?MemberSectorModel $memberSectorModel = null,
        private ?SectorModel $sectorModel = null,
    ) {
        $this->qrControlModel ??= new QrControlModel();
        $this->memberSectorModel ??= new MemberSectorModel();
        $this->sectorModel ??= new SectorModel();
    }

    /**
     * Persists a single family. Caller must already be inside a transaction.
     * $headPayload is the `member` row for the head (relationship forced to
     * 'HEAD'). $controlNo is the paper QR control number for the head, taken
     * from the import's "QR Number" column; null for manual entry. Returns the
     * new head member ID.
     *
     * @param list<array{payload: array, serviceIds: int[]}> $memberPayloads One entry per additional member.
     * @param int[] $headServiceIds Service IDs to assign to the head.
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
        $headPayload['relationship'] = 'HEAD';

        // Sectors ride along on the payload but are their own table since V22,
        // so they come off before the `member` row is written.
        $headSectorIds = $this->takeSectorIds($headPayload);

        $headId = $this->memberModel->createHead($headPayload);

        if ($headId === false) {
            throw new FamilyRecordWriteException('Head of family could not be saved. Please check required fields.');
        }

        if ($controlNo !== null && $controlNo > 0) {
            $this->qrControlModel->assign($controlNo, $headId);
        }

        foreach ($memberPayloads as $entry) {
            $payload = $entry['payload'] ?? [];
            $memberSectorIds = $this->takeSectorIds($payload);
            $memberId = $this->memberModel->addFamilyMember($headId, $payload);

            if ($memberId === false) {
                throw new FamilyRecordWriteException('One family member could not be saved.');
            }

            $this->assignSectors($memberId, $memberSectorIds, 'one family member');
            $this->assignServices($memberId, $entry['serviceIds'] ?? [], 'one family member');
        }

        $this->assignSectors($headId, $headSectorIds, 'the head of family');
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
     * Appends ONE member to an existing family (its head already on file), with its
     * services, and writes a FAMILY_UPDATED audit row. Caller must already be inside a
     * transaction and must have confirmed the head exists. Returns the new member ID.
     *
     * @param int[] $serviceIds Services to assign to the added member.
     * @throws FamilyRecordWriteException on any insert/assignment failure.
     */
    public function appendMember(
        int $headId,
        array $memberPayload,
        array $serviceIds,
        int $operatorUserId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $auditSuffix = '',
    ): int {
        $sectorIds = $this->takeSectorIds($memberPayload);
        $memberId  = $this->memberModel->addFamilyMember($headId, $memberPayload);

        if ($memberId === false) {
            throw new FamilyRecordWriteException('The member could not be added to the family.');
        }

        $this->assignSectors($memberId, $sectorIds, 'the added member');
        $this->assignServices($memberId, $serviceIds, 'the added member');

        if ($this->auditModel->hasTable()) {
            $name = trim(((string) ($memberPayload['firstname'] ?? '')) . ' ' . ((string) ($memberPayload['lastname'] ?? '')));

            $this->auditModel->logAction(
                $operatorUserId,
                $headId,
                'FAMILY_UPDATED',
                'Added ' . ($name !== '' ? $name : 'a member') . ' to an existing family.',
                $ipAddress,
                $userAgent,
                'Added member: ' . $name . $auditSuffix,
            );
        }

        return $memberId;
    }

    /**
     * Pulls the sector ids off a member payload, leaving a payload of `member`
     * columns only. Accepts the key under either name so a caller assembling a
     * payload by hand cannot silently drop the family's sectors.
     *
     * @param array<string, mixed> $payload Modified in place.
     * @return int[]
     */
    private function takeSectorIds(array &$payload): array
    {
        $ids = $payload['sector_ids'] ?? $payload['sectorIds'] ?? [];
        unset($payload['sector_ids'], $payload['sectorIds']);

        return SectorIds::normalize($ids);
    }

    /**
     * Replaces a member's sectors, skipping ids with no sector row - the same
     * tolerance assignServices() applies, and necessary here because the
     * junction's foreign key would otherwise reject the whole write over one
     * stale id in an imported sheet.
     *
     * @param int[] $sectorIds
     */
    private function assignSectors(int $memberId, array $sectorIds, string $who): void
    {
        $known = array_filter(
            $sectorIds,
            fn (int $sectorId): bool => $sectorId > 0 && $this->sectorModel->existsById($sectorId)
        );

        if (! $this->memberSectorModel->replaceForMember($memberId, $known)) {
            throw new FamilyRecordWriteException('The selected sectors could not be assigned to ' . $who . '.');
        }
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
