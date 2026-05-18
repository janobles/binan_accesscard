<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

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

        $db = db_connect();
        foreach (['member', 'sector', 'services', 'member_services', 'audit_trails'] as $table) {
            if (! $db->tableExists($table)) {
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

        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $userId = (int) session()->get('user_id');

        $db->transStart();

        $headId = $memberModel->createHead($this->memberPayload('head_'));

        if ($headId === false) {
            $db->transRollback();

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
                $db->transRollback();

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
        }

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0 || ! $this->serviceExists($serviceId)) {
                continue;
            }

            $memberServiceModel->assignService($headId, $serviceId);
        }

        if ($db->tableExists('audit_trails')) {
            (new AuditTrailsModel())->logAction(
                $userId,
                $headId,
                'FAMILY_CREATED',
                'Created family profile for ' . trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')) . '.',
                $this->request->getIPAddress()
            );
        }

        $db->transComplete();

        if (! $db->transStatus()) {
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

    private function serviceExists(int $serviceId): bool
    {
        return db_connect()->table('services')
            ->where('serviceID', $serviceId)
            ->countAllResults() > 0;
    }
}
