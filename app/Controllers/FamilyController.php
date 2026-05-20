<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

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
                'formAction' => site_url('admin/manage-family/update/' . $headId),
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
            return redirect()->back()->withInput()->with('error', 'The accesscard database is missing required tables from accesscardV1.4.sql.');
        }

        $rules = [
            'head_firstname' => 'required|max_length[100]',
            'head_middlename' => 'required|max_length[50]',
            'head_lastname' => 'required|max_length[100]',
            'head_birthday' => 'required|valid_date[Y-m-d]',
            'head_sex' => 'required|in_list[Male,Female]',
            'sectorID' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $head = $memberModel->find($headId);

        if ($head === null || (int) ($head['headID'] ?? 0) !== $headId) {
            return redirect()->back()->with('error', 'Family record not found.');
        }

        $members = $this->request->getPost('members');
        $headServiceIds = $this->request->getPost('service_ids');

        if (! is_array($members)) {
            $members = [];
        }

        if (! is_array($headServiceIds)) {
            $headServiceIds = [];
        }

        $memberModel->beginTransaction();

        if (! $memberModel->updateHead($headId, $this->memberPayload('head_'))) {
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

        foreach ($headServiceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId <= 0 || ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($headId, $serviceId) === false) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'Could not assign selected head services.');
            }
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return redirect()->back()->withInput()->with('error', 'A family member could not be updated.');
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

                    return redirect()->back()->withInput()->with('error', 'A selected service could not be assigned to one family member.');
                }
            }
        }

        if ($auditModel->hasTable()) {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                $headId,
                'FAMILY_UPDATED',
                'Updated family profile for ' . trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')) . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        }

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            return redirect()->back()->withInput()->with('error', 'The family update was not saved.');
        }

        return redirect()->to(site_url('admin/dashboard'))->with('success', 'Family and member data updated successfully.');
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

        $rules = [
            'head_firstname' => 'required|max_length[100]',
            'head_middlename' => 'required|max_length[50]',
            'head_lastname' => 'required|max_length[100]',
            'head_birthday' => 'required|valid_date[Y-m-d]',
            'head_sex' => 'required|in_list[Male,Female]',
            'sectorID' => 'required|is_natural_no_zero',
        ];

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

        $members = $this->request->getPost('members');
        $serviceIds = $this->request->getPost('service_ids');

        if (! is_array($members)) {
            $members = [];
        }

        if (! is_array($serviceIds)) {
            $serviceIds = [];
        }

        $userId = (int) session()->get('user_id');

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

            $memberServiceModel->assignService($headId, $serviceId);
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
                'message' => 'Family and member data saved successfully.',
                'csrf' => csrf_hash(),
            ]);
        }

        return redirect()->back()->with('success', 'Family and member data saved successfully.');
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
            'relationship' => $prefix === 'head_' ? 'Head' : trim((string) $this->request->getPost($prefix . 'relationship')),
            'sectorID' => (int) $this->request->getPost('sectorID'),
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
            'relationship' => $this->nullableText($member['relationship'] ?? 'Member'),
            'sectorID' => (int) ($member['sectorID'] ?? $this->request->getPost('sectorID')),
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
            if ((int) ($member['memberID'] ?? 0) === $headId) {
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

}
