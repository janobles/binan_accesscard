<?php

namespace App\Controllers;

use App\Libraries\SectorIds;
use App\Libraries\DashboardPageBuilder;
use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Handles family registration submissions from admin and employee views.
 *
 * The controller validates the request and delegates database writes to models.
 */
class FamilyController extends BaseController
{
    public function listFamilies(): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $pageBuilder = new DashboardPageBuilder($this->request);
        $viewData = (string) session()->get('role') === 'User'
            ? $pageBuilder->buildEmployeeRecordListViewData()
            : $pageBuilder->buildAdminRecordListViewData();

        return view('Dashboard/familyform/family-list', $viewData);
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
            $message = 'The accesscard database is missing required tables from accesscardV1.4.sql.';

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

        $entryType = $this->entryType();
        $rules = $this->rulesForEntryType($entryType);

        if (! $this->validate($rules)) {
            $message = implode(' ', $this->validator->getErrors());

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }

        $serviceIds = $this->request->getPost('service_ids');

        if (! is_array($serviceIds)) {
            $serviceIds = [];
        }

        $members = $this->request->getPost('members');

        if (! is_array($members)) {
            $members = [];
        }

        $userId = (int) session()->get('user_id');
        $successMessage = 'Family record saved successfully.';

        $memberModel->beginTransaction();

        $headId = $memberModel->createHead($this->memberPayload('head_'));

        if ($headId === false) {
            $memberModel->rollbackTransaction();

            $message = 'Head of family could not be saved. Please check required fields.';

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                $message = 'One family member could not be saved.';

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

            $memberServiceIds = $member['service_ids'] ?? [];

            if (! is_array($memberServiceIds)) {
                $memberServiceIds = [];
            }

            foreach ($memberServiceIds as $memberServiceId) {
                $memberServiceId = (int) $memberServiceId;

                if ($memberServiceId <= 0 || ! $serviceModel->existsById($memberServiceId)) {
                    continue;
                }

                if ($memberServiceModel->assignService($memberId, $memberServiceId) === false) {
                    $memberModel->rollbackTransaction();

                    $message = 'A selected service could not be assigned to one family member.';

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
            }
        }

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0 || ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($headId, $serviceId) === false) {
                $memberModel->rollbackTransaction();

                $message = 'A selected service could not be assigned to the head of family.';

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
        }

        if ($auditModel->hasTable()) {
            // Tracks the creating operator plus client IP and browser agent.
            $auditModel->logAction(
                $userId,
                $headId,
                'FAMILY_CREATED',
                'Created family profile for ' . trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')) . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        }

        $memberModel->completeTransaction();

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

    public function viewFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $family = $this->familyData($headId);

        if ($family === null) {
            return $this->familyNotFoundResponse();
        }

        return view('Dashboard/familyform/family-view', $this->familyViewData(
            $family['head'],
            $family['members'],
            $family['serviceMap']
        ));
    }

    public function editFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $family = $this->familyData($headId);

        if ($family === null) {
            return $this->familyNotFoundResponse();
        }

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            [
                'embeddedInModal' => true,
                'existingMembers' => $this->editableMembers($family['members'], $family['serviceMap']),
                'familyRecord' => $family['head'],
                'formAction' => site_url($this->familyRouteBase() . '/update/' . $headId),
                'headServiceIds' => $family['serviceMap'][$headId] ?? [],
                'submitButtonLabel' => 'Update Record',
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
        $family = $this->familyData($headId);

        if ($family === null) {
            return $this->redirectToFamilyList('Family record was not found.', false);
        }

        if (! $this->validate($this->rulesForEntryType('head'))) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $members = $this->request->getPost('members');
        $serviceIds = $this->request->getPost('service_ids');
        $members = is_array($members) ? $members : [];
        $serviceIds = is_array($serviceIds) ? $serviceIds : [];
        $existingMemberIds = $memberModel->getFamilyMemberIds($headId);

        $memberModel->beginTransaction();

        if (
            ! $memberServiceModel->deleteByMemberIds($existingMemberIds)
            || ! $memberModel->deleteFamilyMembersExceptHead($headId)
            || ! $memberModel->updateHead($headId, $this->memberPayload('head_'))
            || ! $this->assignServices($memberServiceModel, $serviceModel, $headId, $serviceIds)
        ) {
            $memberModel->rollbackTransaction();

            return $this->redirectToFamilyList('Family record could not be updated.', false);
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if (
                $memberId === false
                || ! $this->assignServices($memberServiceModel, $serviceModel, $memberId, $member['service_ids'] ?? [])
            ) {
                $memberModel->rollbackTransaction();

                return $this->redirectToFamilyList('One family member could not be updated.', false);
            }
        }

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            return $this->redirectToFamilyList('Family record could not be updated.', false);
        }

        $this->auditFamilyAction(
            $headId,
            'FAMILY_UPDATED',
            'Updated family profile for ' . $this->familyName($family['head']) . '.'
        );

        return $this->redirectToFamilyList('Family record updated successfully.');
    }

    public function archive(int $headId): RedirectResponse
    {
        $guard = $this->requireAdminFamilyAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->changeFamilyVisibility($headId, 'archive');
    }

    public function restore(int $headId): RedirectResponse
    {
        $guard = $this->requireAdminFamilyAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->changeFamilyVisibility($headId, 'restore');
    }

    public function delete(int $headId): RedirectResponse
    {
        $guard = $this->requireEmployeeFamilyAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->changeFamilyVisibility($headId, 'delete');
    }

    private function requireFamilyEntryAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = (string) session()->get('role');

        if (in_array($role, ['Developer', 'Admin', 'User'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to add family records.');
    }

    private function requireAdminFamilyAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (in_array((string) session()->get('role'), ['Developer', 'Admin'], true)) {
            return null;
        }

        return $this->redirectToFamilyList('Admin or Developer access is required.', false);
    }

    private function requireEmployeeFamilyAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if ((string) session()->get('role') === 'User') {
            return null;
        }

        return $this->redirectToFamilyList('Employee access is required.', false);
    }

    private function familyData(int $headId): ?array
    {
        if ($headId <= 0) {
            return null;
        }

        $members = (new MemberModel())->getFamilyMembers($headId);
        $head = $this->familyHead($members, $headId);

        if ($head === null) {
            return null;
        }

        $serviceMap = (new MemberServiceModel())->getServiceIdsByMemberIds(array_column($members, 'memberID'));

        return [
            'head' => $head,
            'members' => $members,
            'serviceMap' => $serviceMap,
        ];
    }

    private function familyHead(array $members, int $headId): ?array
    {
        foreach ($members as $member) {
            if ((int) ($member['memberID'] ?? 0) === $headId && (int) ($member['headID'] ?? 0) === $headId) {
                return $member;
            }
        }

        return null;
    }

    private function editableMembers(array $members, array $serviceMap): array
    {
        $editableMembers = [];

        foreach ($members as $member) {
            $memberId = (int) ($member['memberID'] ?? 0);

            if ($memberId <= 0 || $memberId === (int) ($member['headID'] ?? 0)) {
                continue;
            }

            $member['salary'] = $member['Salary'] ?? null;
            $member['sector_ids'] = SectorIds::normalize($member['sectorID'] ?? null);
            $member['service_ids'] = $serviceMap[$memberId] ?? [];
            $editableMembers[] = $member;
        }

        return $editableMembers;
    }

    private function familyViewData(array $head, array $members, array $serviceMap): array
    {
        $serviceIds = [];

        foreach ($serviceMap as $ids) {
            $serviceIds = array_merge($serviceIds, (array) $ids);
        }

        $serviceNameMap = (new ServiceModel())->getNameMapByIds($serviceIds);
        $memberViews = [];

        foreach ($members as $member) {
            if ((int) ($member['memberID'] ?? 0) === (int) ($head['memberID'] ?? 0)) {
                continue;
            }

            $memberViews[] = $this->personViewData($member, $serviceMap, $serviceNameMap);
        }

        return [
            'headView' => $this->personViewData($head, $serviceMap, $serviceNameMap),
            'memberViews' => $memberViews,
        ];
    }

    private function personViewData(array $person, array $serviceMap, array $serviceNameMap): array
    {
        $memberId = (int) ($person['memberID'] ?? 0);
        $createdAt = strtotime((string) ($person['dt_created'] ?? ''));
        $services = [];

        foreach ($serviceMap[$memberId] ?? [] as $serviceId) {
            if (isset($serviceNameMap[(int) $serviceId])) {
                $services[] = $serviceNameMap[(int) $serviceId];
            }
        }

        return [
            'createdDate' => $createdAt === false ? '-' : date('Y-m-d', $createdAt),
            'createdTime' => $createdAt === false ? '-' : date('h:i A', $createdAt),
            'details' => [
                ['label' => 'Birthday', 'value' => $person['birthday'] ?? '-'],
                ['label' => 'Sex', 'value' => $person['sex'] ?? '-'],
                ['label' => 'Civil status', 'value' => $person['civilstatus'] ?? '-'],
                ['label' => 'Contact number', 'value' => $person['contactnumber'] ?? '-'],
                ['label' => 'Religion', 'value' => $person['religion'] ?? '-'],
                ['label' => 'Education', 'value' => $person['education'] ?? '-'],
                ['label' => 'Job', 'value' => $person['job'] ?? '-'],
                ['label' => 'Monthly income', 'value' => $person['Salary'] ?? '-'],
                ['label' => 'Address', 'value' => $person['address'] ?? '-'],
            ],
            'fullName' => $this->familyName($person),
            'relationship' => (string) ($person['relationship'] ?? 'Member'),
            'sectorName' => (string) ($person['sector_name'] ?? ''),
            'services' => $services,
        ];
    }

    private function assignServices(
        MemberServiceModel $memberServiceModel,
        ServiceModel $serviceModel,
        int $memberId,
        mixed $serviceIds
    ): bool {
        if (! is_array($serviceIds)) {
            return true;
        }

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0 || ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($memberId, $serviceId) === false) {
                return false;
            }
        }

        return true;
    }

    private function changeFamilyVisibility(int $headId, string $action): RedirectResponse
    {
        $memberModel = new MemberModel();
        $family = $action === 'restore'
            ? $memberModel->getFamilyMembers($headId, 'archived')
            : $memberModel->getFamilyMembers($headId);
        $head = $this->familyHead($family, $headId);

        if ($head === null) {
            return $this->redirectToFamilyList('Family record was not found.', false);
        }

        $updated = match ($action) {
            'archive' => $memberModel->archiveFamily($headId),
            'restore' => $memberModel->restoreFamily($headId),
            'delete' => $memberModel->deleteFamilyRecord($headId),
            default => false,
        };

        if (! $updated) {
            return $this->redirectToFamilyList('Family record could not be updated.', false);
        }

        [$auditAction, $verb] = match ($action) {
            'archive' => ['FAMILY_ARCHIVED', 'Archived'],
            'restore' => ['FAMILY_RESTORED', 'Restored'],
            default => ['FAMILY_DELETED', 'Deleted'],
        };

        $this->auditFamilyAction($headId, $auditAction, $verb . ' family profile for ' . $this->familyName($head) . '.');

        return $this->redirectToFamilyList($verb . ' family record successfully.');
    }

    private function auditFamilyAction(int $headId, string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                $headId,
                $action,
                $description,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            log_message('error', 'Audit trail skipped: ' . $exception->getMessage());
        }
    }

    private function familyName(array $member): string
    {
        $name = trim(implode(' ', array_filter([
            (string) ($member['firstname'] ?? ''),
            (string) ($member['middlename'] ?? ''),
            (string) ($member['lastname'] ?? ''),
            (string) ($member['suffix'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));

        return $name === '' ? 'record #' . (int) ($member['memberID'] ?? 0) : $name;
    }

    private function familyRouteBase(): string
    {
        return (string) session()->get('role') === 'User'
            ? 'employee/manage-family'
            : 'admin/manage-family';
    }

    private function redirectToFamilyList(string $message, bool $success = true): RedirectResponse
    {
        return redirect()
            ->to(site_url((string) session()->get('role') === 'User' ? 'employee/manage-records' : 'admin/manage-records'))
            ->with($success ? 'success' : 'error', $message);
    }

    private function familyNotFoundResponse(): string|RedirectResponse
    {
        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            $this->response->setStatusCode(404);

            return '<div class="alert alert-danger mb-0">Family record was not found.</div>';
        }

        return $this->redirectToFamilyList('Family record was not found.', false);
    }

    private function memberPayload(string $prefix): array
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
            'religion' => $this->nullableText($this->request->getPost($prefix . 'religion')),
            'address' => $this->nullableText($this->request->getPost($prefix . 'address')),
            'relationship' => $prefix === 'head_' ? 'Head' : $this->nullableText($this->request->getPost($prefix . 'relationship')),
            'sectorID' => SectorIds::normalize($this->request->getPost('sector_ids')),
        ];
    }

    private function entryType(): string
    {
        return (string) $this->request->getPost('entry_type') === 'member' ? 'member' : 'head';
    }

    private function rulesForEntryType(string $entryType): array
    {
        $rules = [
            'sector_ids' => 'required|valid_sector_array',
        ];

        if ($entryType === 'member') {
            return $rules + [
                'family_head_id' => 'required|is_natural_no_zero',
                'member_firstname' => 'required|max_length[100]',
                'member_lastname' => 'required|max_length[100]',
                'member_middlename' => 'permit_empty|max_length[50]',
                'member_birthday' => 'permit_empty|valid_date[Y-m-d]',
                'member_sex' => 'permit_empty|in_list[Male,Female]',
            ];
        }

        return $rules + [
            'head_firstname' => 'required|max_length[100]',
            'head_middlename' => 'permit_empty|max_length[50]',
            'head_lastname' => 'required|max_length[100]',
            'head_birthday' => 'required|valid_date[Y-m-d]',
            'head_sex' => 'required|in_list[Male,Female]',
        ];
    }

    private function memberPayloadFromArray(array $member): array
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
            'religion' => $this->nullableText($member['religion'] ?? null),
            'relationship' => $this->nullableText($member['relationship'] ?? 'Member'),
            'sectorID' => SectorIds::normalize($member['sector_ids'] ?? $this->request->getPost('sector_ids')),
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

}
