<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Validates and saves family head or family member registration records.
 */
class FamilyController extends BaseController
{
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
            return $this->validationResponse('The accesscard database is missing required tables from accesscardV1.4.sql.');
        }

        $entryType = $this->entryType();

        if (! $this->validate($this->rulesForEntryType($entryType))) {
            return $this->validationResponse(implode(' ', $this->validator->getErrors()));
        }

        $serviceIds = $this->request->getPost('service_ids');

        if (! is_array($serviceIds)) {
            $serviceIds = [];
        }

        $userId = (int) session()->get('user_id');
        $memberModel->beginTransaction();

        if ($entryType === 'member') {
            $headId = (int) $this->request->getPost('family_head_id');
            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayload('member_'));

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

        if (! is_array($members)) {
            $members = [];
        }

        $headId = $memberModel->createHead($this->memberPayload('head_'));

        if ($headId === false) {
            $memberModel->rollbackTransaction();

            return $this->validationResponse('Head of family could not be saved. Please check required fields.');
        }

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return $this->validationResponse('One family member could not be saved.');
            }

            $memberServiceIds = $member['service_ids'] ?? [];

            if (! is_array($memberServiceIds)) {
                $memberServiceIds = [];
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
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
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

    private function rulesForEntryType(string $entryType): array
    {
        $rules = [
            'sectorID' => 'required|is_natural_no_zero',
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
            'head_middlename' => 'required|max_length[50]',
            'head_lastname' => 'required|max_length[100]',
            'head_birthday' => 'required|valid_date[Y-m-d]',
            'head_sex' => 'required|in_list[Male,Female]',
        ];
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
            'relationship' => $prefix === 'head_' ? 'Head' : ($this->nullableText($this->request->getPost($prefix . 'relationship')) ?? 'Member'),
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

    private function assignServices(
        MemberServiceModel $memberServiceModel,
        ServiceModel $serviceModel,
        int $memberId,
        array $serviceIds
    ): bool {
        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId <= 0 || ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($memberId, $serviceId) === false) {
                return false;
            }
        }

        return true;
    }

    private function auditFamilyAction(
        AuditTrailsModel $auditModel,
        int $userId,
        int $memberId,
        string $action,
        string $description
    ): void {
        if (! $auditModel->hasTable()) {
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
}
