<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner Manage tab: global back-office. Renders the aid-types + all-distributions
 * hub and handles their mutations (aid-type CRUD, distribution void). Every action
 * is Scanner/Admin/Developer-only and writes an audit_trails row.
 */
class ManageController extends BaseController
{
    /** GET scanner/manage — the two-section hub page. */
    public function index(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        $canManage = in_array($role, ['Developer', 'Admin'], true);

        return view('Scanner/manage', [
            'activeTab'         => 'manage',
            'pageTitle'         => 'Manage',
            'username'          => session('username') ?? 'Scanner',
            'aidTypes'          => model(AidTypeModel::class)->all(),
            'distributions'     => model(AidDistributionModel::class)->allDistributions(),
            'currentRole'       => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass'  => $canManage ? 'developer' : 'admin',
            'sidebarUserUrl'    => site_url('admin/dashboard'),
            'navActive'         => ['scanner-manage' => 'active'],
        ]);
    }

    /** POST scanner/aid-types/create */
    public function createAidType(): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('scanner/manage')->with('error', 'Aid type name is required.');
        }

        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to add aid type.');
        }

        $this->audit('Created aid type "' . $name . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type added.');
    }

    /** POST scanner/aid-types/archive/{id} */
    public function archiveAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to archive aid type.');
        }

        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type archived.');
    }

    /** POST scanner/aid-types/restore/{id} */
    public function restoreAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to restore aid type.');
        }

        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type restored.');
    }

    /** POST scanner/aid-types/delete/{id} — only when never referenced. */
    public function deleteAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $used = model(AidDistributionModel::class)
            ->where('aid_type_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->to('scanner/manage')
                ->with('error', 'Cannot delete: aid type is used by ' . $used . ' distribution(s). Archive it instead.');
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->delete($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to delete aid type.');
        }

        $this->audit('Deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type deleted.');
    }

    /** POST scanner/distributions/void/{id} — hard-delete a wrong claim. */
    public function voidDistribution(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $row = model(AidDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('scanner/manage')->with('error', 'Distribution not found.');
        }

        if (! model(AidDistributionModel::class)->void($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to void distribution.');
        }

        $this->audit(
            'Voided aid distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (int) ($row['control_no'] ?? 0) . ', aid type ID ' . (int) ($row['aid_type_id'] ?? 0) . ', claim date ' . (string) ($row['claim_date'] ?? '')
        );

        return redirect()->to('scanner/manage')->with('success', 'Distribution voided.');
    }

    /** Write an audit_trails row for the acting scanner. */
    private function audit(string $action, int $memberId = 0, ?string $detail = null): void
    {
        $userId = (int) (session('user_id') ?? 0);
        (new AuditTrailsModel())->logAction(
            $userId,
            $memberId > 0 ? $memberId : null,
            $action,
            null,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $detail
        );
    }
}
