<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\ServiceModel;
use App\Support\SectorIds;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Validates and saves family head or family member registration records.
 */
class FamilyController extends BaseController
{
    public function listFamilies(): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $keyword = trim((string) $this->request->getGet('q'));
        $families = (new MemberModel())->searchFamilies($keyword === '' ? null : $keyword);

        return view('Dashboard/family-list', [
            'families' => $families,
            'keyword' => $keyword,
            'routeBase' => $this->familyRouteBase(),
        ]);
    }

    public function viewFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $familyData = $this->buildFamilyData($headId);

        if ($familyData === null) {
            return '<div class="alert alert-warning mb-0">Family record not found.</div>';
        }

        return view('Dashboard/family-view', $familyData);
    }

    public function editFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $familyData = $this->buildFamilyData($headId);

        if ($familyData === null) {
            return '<div class="alert alert-warning mb-0">Family record not found.</div>';
        }

        $familyOptions = (new FamilyFormOptionsModel())->getViewData();
        $head = $familyData['head'];
        $members = $familyData['members'];
        $serviceMap = $familyData['serviceMap'];
        $headServiceIds = $serviceMap[(int) ($head['memberID'] ?? 0)] ?? [];

        foreach ($members as $index => $member) {
            $memberId = (int) ($member['memberID'] ?? 0);
            $members[$index]['service_ids'] = $serviceMap[$memberId] ?? [];
        }

        return view('Dashboard/familyform', array_merge(
            $familyOptions,
            [
                'formAction' => site_url($this->familyRouteBase() . '/update/' . $headId),
                'submitButtonLabel' => 'Update Family Data',
                'familyRecord' => $head,
                'existingMembers' => $members,
                'headServiceIds' => $headServiceIds,
                'showManageFamilyListButton' => true,
            ]
        ));
    }

    public function update(int $headId): RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $serviceModel = new ServiceModel();
        $auditModel = new AuditTrailsModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            return redirect()->back()->withInput()->with('error', 'The accesscard database is missing required tables from accesscardV3.0.sql.');
        }

        if (! $this->validate($this->rulesForEntryType('head'))) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (! $this->postedSectorIdsHaveValidShape()) {
            return redirect()->back()->withInput()->with('error', 'Sector selections must be submitted as a list.');
        }

        $postedSectorIds = $this->postedSectorIds();
        $sectorIds = SectorIds::normalize($postedSectorIds);

        if ($sectorIds === []) {
            return redirect()->back()->withInput()->with('error', 'Please select at least one sector name.');
        }

        if (SectorIds::hasMalformedIds($postedSectorIds) || ! $this->sectorIdsExist($sectorIds)) {
            return redirect()->back()->withInput()->with('error', 'One or more selected sectors are invalid.');
        }

        $head = $memberModel->find($headId);

        if ($head === null || (int) ($head['headID'] ?? 0) !== $headId) {
            return redirect()->back()->with('error', 'Family record not found.');
        }

        $members = $this->request->getPost('members');
        $headServiceIds = $this->normalizeIdListPayload($this->request->getPost('service_ids'));

        if ($members !== null && ! is_array($members)) {
            return redirect()->back()->withInput()->with('error', 'Family member entries must be submitted as a list.');
        }

        if (! is_array($members)) {
            $members = [];
        }

        if ($headServiceIds === null || ! $serviceModel->idsExist($headServiceIds)) {
            return redirect()->back()->withInput()->with('error', 'One or more selected head services are invalid.');
        }

        $memberModel->beginTransaction();

        if (! $memberModel->updateHead($headId, $this->memberPayload('head_', $sectorIds))) {
            $memberModel->rollbackTransaction();

            return redirect()->back()->withInput()->with('error', 'Head of family could not be updated.');
        }

        $existingMemberIds = $memberModel->getFamilyMemberIds($headId);

        if (! $memberServiceModel->deleteByMemberIds($existingMemberIds)) {
            $memberModel->rollbackTransaction();

            return redirect()->back()->withInput()->with('error', 'Could not update family services.');
        }

        if (! $memberModel->deleteFamilyMembersExceptHead($headId)) {
            $memberModel->rollbackTransaction();

            return redirect()->back()->withInput()->with('error', 'Could not refresh family members.');
        }

        if (! $this->assignServices($memberServiceModel, $serviceModel, $headId, $headServiceIds)) {
            $memberModel->rollbackTransaction();

            return redirect()->back()->withInput()->with('error', 'Could not assign selected head services.');
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberSectorIds = $this->memberSectorIdsFromArray($member, $sectorIds);

            if ($memberSectorIds === null) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'One family member has an invalid sector selection.');
            }

            $memberServiceIds = $this->normalizeIdListPayload($member['service_ids'] ?? null);

            if ($memberServiceIds === null || ! $serviceModel->idsExist($memberServiceIds)) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'One family member has an invalid service selection.');
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member, $memberSectorIds));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'A family member could not be updated.');
            }

            if (! $this->assignServices($memberServiceModel, $serviceModel, $memberId, $memberServiceIds)) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'A selected service could not be assigned to one family member.');
            }
        }

        $this->auditFamilyAction(
            $auditModel,
            (int) session()->get('user_id'),
            $headId,
            'FAMILY_UPDATED',
            'Updated family profile for ' . trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')) . '.'
        );

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            return redirect()->back()->withInput()->with('error', 'The family update was not saved.');
        }

        return redirect()->to(site_url($this->familyDashboardPath()))->with('success', 'Family and member data updated successfully.');
    }

    public function store()
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'You do not have permission to add family records.',
                        'csrf' => csrf_hash(),
                    ]);
            }

            return $guard;
        }

        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $serviceModel = new ServiceModel();
        $auditModel = new AuditTrailsModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            return $this->validationResponse('The accesscard database is missing required tables from accesscardV3.0.sql.');
        }

        $entryType = $this->entryType();

        if (! $this->validate($this->rulesForEntryType($entryType))) {
            return $this->validationResponse(implode(' ', $this->validator->getErrors()));
        }

        if (! $this->postedSectorIdsHaveValidShape()) {
            return $this->validationResponse('Sector selections must be submitted as a list.');
        }

        $postedSectorIds = $this->postedSectorIds();
        $sectorIds = SectorIds::normalize($postedSectorIds);

        if ($sectorIds === []) {
            return $this->validationResponse('Please select at least one sector name.');
        }

        if (SectorIds::hasMalformedIds($postedSectorIds) || ! $this->sectorIdsExist($sectorIds)) {
            return $this->validationResponse('One or more selected sectors are invalid.');
        }

        $serviceIds = $this->normalizeIdListPayload($this->request->getPost('service_ids'));

        if ($serviceIds === null || ! $serviceModel->idsExist($serviceIds)) {
            return $this->validationResponse('One or more selected services are invalid.');
        }

        $userId = (int) session()->get('user_id');
        $memberModel->beginTransaction();

        if ($entryType === 'member') {
            $headId = (int) $this->request->getPost('family_head_id');
            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayload('member_', $sectorIds));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('Family member could not be saved. Please check the selected family head and required fields.');
            }

            if (! $this->assignServices($memberServiceModel, $serviceModel, $memberId, $serviceIds)) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('A selected service could not be assigned to this family member.');
            }

            $this->auditFamilyAction(
                $auditModel,
                $userId,
                $memberId,
                'FAMILY_MEMBER_CREATED',
                'Added family member ' . trim((string) $this->request->getPost('member_firstname')) . ' ' . trim((string) $this->request->getPost('member_lastname')) . '.'
            );

            $memberModel->completeTransaction();

            return $this->familyResponse($memberModel, 'Family member and services saved successfully.');
        }

        $members = $this->request->getPost('members');

        if ($members !== null && ! is_array($members)) {
            return $this->validationResponse('Family member entries must be submitted as a list.');
        }

        if (! is_array($members)) {
            $members = [];
        }

        $headId = $memberModel->createHead($this->memberPayload('head_', $sectorIds));

        if ($headId === false) {
            $memberModel->rollbackTransaction();

            return $this->validationResponse('Head of family could not be saved. Please check required fields.');
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberSectorIds = $this->memberSectorIdsFromArray($member, $sectorIds);

            if ($memberSectorIds === null) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('One family member has an invalid sector selection.');
            }

            $memberServiceIds = $this->normalizeIdListPayload($member['service_ids'] ?? null);

            if ($memberServiceIds === null || ! $serviceModel->idsExist($memberServiceIds)) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('One family member has an invalid service selection.');
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member, $memberSectorIds));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('One family member could not be saved.');
            }

            if (! $this->assignServices($memberServiceModel, $serviceModel, $memberId, $memberServiceIds)) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('A selected service could not be assigned to one family member.');
            }
        }

        if (! $this->assignServices($memberServiceModel, $serviceModel, $headId, $serviceIds)) {
            $memberModel->rollbackTransaction();

            return $this->validationResponse('A selected service could not be assigned to the family head.');
        }

        $this->auditFamilyAction(
            $auditModel,
            $userId,
            $headId,
            'FAMILY_CREATED',
            'Created family profile for ' . trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')) . '.'
        );

        $memberModel->completeTransaction();

        return $this->familyResponse($memberModel, 'Family and member data saved successfully.');
    }

    private function validationResponse(string $message)
    {
        if ($this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $message,
                    'csrf' => csrf_hash(),
                ]);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    private function familyResponse(MemberModel $memberModel, string $successMessage)
    {
        if (! $memberModel->transactionStatus()) {
            $message = 'The family form was not saved.';

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $successMessage,
                'csrf' => csrf_hash(),
            ]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    private function requireFamilyEntryAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Please login first.');
        }

        if (! $this->sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('login'))->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        $role = (string) session()->get('role');

        if (in_array($role, ['Developer', 'Admin', 'User'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to add family records.');
    }

    private function entryType(): string
    {
        return (string) $this->request->getPost('entry_type') === 'member' ? 'member' : 'head';
    }

    private function postedSectorIds(): mixed
    {
        $sectorIds = $this->request->getPost('sector_ids');

        return $sectorIds !== null ? $sectorIds : $this->request->getPost('sectorID');
    }

    private function postedSectorIdsHaveValidShape(): bool
    {
        $sectorIds = $this->request->getPost('sector_ids');

        if ($sectorIds !== null) {
            return is_array($sectorIds) && $this->isListArray($sectorIds);
        }

        $legacySectorId = $this->request->getPost('sectorID');

        return ! is_array($legacySectorId) || $this->isListArray($legacySectorId);
    }

    private function rulesForEntryType(string $entryType): array
    {
        if ($entryType === 'member') {
            return array_merge(
                ['family_head_id' => 'required|is_natural_no_zero'],
                $this->prefixedRules('member_', MemberModel::personValidationRules())
            );
        }

        return $this->prefixedRules('head_', MemberModel::personValidationRules(true));
    }

    private function prefixedRules(string $prefix, array $rules): array
    {
        $prefixed = [];

        foreach ($rules as $field => $rule) {
            $prefixed[$prefix . $field] = $rule;
        }

        return $prefixed;
    }

    private function memberPayload(string $prefix, array $sectorIds): array
    {
        return [
            'firstname' => trim((string) $this->request->getPost($prefix . 'firstname')),
            'middlename' => trim((string) $this->request->getPost($prefix . 'middlename')),
            'lastname' => trim((string) $this->request->getPost($prefix . 'lastname')),
            'suffix' => $this->nullableText($this->request->getPost($prefix . 'suffix')),
            'birthday' => $this->request->getPost($prefix . 'birthday'),
            'civilstatus' => $this->nullableText($this->request->getPost($prefix . 'civilstatus')),
            'sex' => $this->nullableText($this->request->getPost($prefix . 'sex')),
            'education' => $this->nullableText($this->request->getPost($prefix . 'education')),
            'job' => $this->nullableText($this->request->getPost($prefix . 'job')),
            'Salary' => $this->moneyOrNull($this->request->getPost($prefix . 'salary')),
            'contactnumber' => $this->nullableText($this->request->getPost($prefix . 'contactnumber')),
            'relationship' => $prefix === 'head_' ? 'Head' : ($this->nullableText($this->request->getPost($prefix . 'relationship')) ?? 'Member'),
            'sectorID' => SectorIds::toStorage($sectorIds),
        ];
    }

    private function memberPayloadFromArray(array $member, array $sectorIds): array
    {
        return [
            'firstname' => trim((string) ($member['firstname'] ?? '')),
            'middlename' => trim((string) ($member['middlename'] ?? '')),
            'lastname' => trim((string) ($member['lastname'] ?? '')),
            'suffix' => $this->nullableText($member['suffix'] ?? null),
            'birthday' => $member['birthday'] ?? null,
            'civilstatus' => $this->nullableText($member['civilstatus'] ?? null),
            'sex' => $this->nullableText($member['sex'] ?? null),
            'education' => $this->nullableText($member['education'] ?? null),
            'job' => $this->nullableText($member['job'] ?? null),
            'Salary' => $this->moneyOrNull($member['salary'] ?? null),
            'contactnumber' => $this->nullableText($member['contactnumber'] ?? null),
            'relationship' => $this->nullableText($member['relationship'] ?? 'Member'),
            'sectorID' => SectorIds::toStorage($sectorIds),
        ];
    }

    private function hasMemberData(array $member): bool
    {
        return trim((string) ($member['firstname'] ?? '')) !== ''
            && trim((string) ($member['lastname'] ?? '')) !== '';
    }

    private function moneyOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assignServices(
        MemberServiceModel $memberServiceModel,
        ServiceModel $serviceModel,
        int $memberId,
        array $serviceIds
    ): bool {
        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0 || ! $serviceModel->existsById($serviceId)) {
                return false;
            }

            if ($memberServiceModel->assignService($memberId, $serviceId) === false) {
                return false;
            }
        }

        return true;
    }

    private function sectorIdsExist(array $sectorIds): bool
    {
        if ($sectorIds === []) {
            return false;
        }

        $db = db_connect();

        if (! $db->tableExists('sector')) {
            return false;
        }

        $existingCount = $db->table('sector')
            ->whereIn('sectorID', $sectorIds)
            ->countAllResults();

        return $existingCount === count($sectorIds);
    }

    private function memberSectorIdsFromArray(array $member, array $fallbackSectorIds): ?array
    {
        if (array_key_exists('sector_ids', $member)) {
            if (! is_array($member['sector_ids']) || ! $this->isListArray($member['sector_ids'])) {
                return null;
            }

            $sectorIds = $member['sector_ids'];
        } elseif (array_key_exists('sectorID', $member)) {
            $sectorIds = $member['sectorID'];
        } else {
            return $fallbackSectorIds;
        }

        if (SectorIds::hasMalformedIds($sectorIds)) {
            return null;
        }

        $normalizedSectorIds = SectorIds::normalize($sectorIds);

        if ($normalizedSectorIds === []) {
            return null;
        }

        return $this->sectorIdsExist($normalizedSectorIds) ? $normalizedSectorIds : null;
    }

    private function normalizeIdListPayload(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value) || ! $this->isListArray($value)) {
            return null;
        }

        $ids = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                return null;
            }

            $item = trim((string) $item);

            if ($item === '' || ! ctype_digit($item)) {
                return null;
            }

            $id = (int) $item;

            if ($id < 0) {
                return null;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private function auditFamilyAction(
        AuditTrailsModel $auditModel,
        int $userId,
        int $memberId,
        string $action,
        string $description
    ): void {
        if (! $auditModel->hasTable() || ! $this->userExists($userId)) {
            return;
        }

        $auditModel->logAction(
            $userId,
            $memberId,
            $action,
            $description,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );
    }

    private function sessionUserExists(): bool
    {
        return $this->userExists((int) session()->get('user_id'));
    }

    private function buildFamilyData(int $headId): ?array
    {
        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $serviceModel = new ServiceModel();

        $familyMembers = $memberModel->getFamilyMembers($headId);

        if ($familyMembers === []) {
            return null;
        }

        $head = null;
        $members = [];

        foreach ($familyMembers as $member) {
            $memberId = (int) ($member['memberID'] ?? 0);
            $member['sector_ids'] = SectorIds::normalize($member['sectorID'] ?? null);

            if ($memberId === $headId) {
                $head = $member;

                continue;
            }

            $members[] = $member;
        }

        if ($head === null) {
            return null;
        }

        $memberIds = array_map(static fn (array $row): int => (int) ($row['memberID'] ?? 0), $familyMembers);
        $serviceMap = $memberServiceModel->getServiceIdsByMemberIds($memberIds);
        $serviceIds = [];

        foreach ($members as $index => $member) {
            $memberId = (int) ($member['memberID'] ?? 0);
            $members[$index]['service_ids'] = $serviceMap[$memberId] ?? [];
        }

        $head['service_ids'] = $serviceMap[(int) ($head['memberID'] ?? 0)] ?? [];

        foreach ($serviceMap as $ids) {
            foreach ((array) $ids as $id) {
                $serviceIds[] = (int) $id;
            }
        }

        $serviceNameMap = $serviceModel->getNameMapByIds($serviceIds);

        return [
            'head' => $head,
            'members' => $members,
            'serviceMap' => $serviceMap,
            'serviceNameMap' => $serviceNameMap,
        ];
    }

    private function familyRouteBase(): string
    {
        $path = trim($this->request->getUri()->getPath(), '/');

        return str_starts_with($path, 'employee/') ? 'employee/manage-family' : 'admin/manage-family';
    }

    private function familyDashboardPath(): string
    {
        $path = trim($this->request->getUri()->getPath(), '/');

        return str_starts_with($path, 'employee/') ? 'employee/workspace' : 'admin/dashboard';
    }

    private function userExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = db_connect();

        if (! $db->tableExists('users')) {
            return false;
        }

        return $db->table('users')
            ->where('userID', $userId)
            ->countAllResults() > 0;
    }
}
