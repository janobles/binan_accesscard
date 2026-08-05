<?php

namespace Tests\Unit;

use App\Libraries\DashboardPageBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards the risk the task-6 dashboard/render-path collapse actually carried:
 * that a role's row-action flags, its own-activity scoping, or its dashboard
 * section visibility quietly drift while the three parallel code paths get
 * merged into one. No database - the role-flag logic and the string checks
 * below don't need one.
 */
final class DashboardRoleGuardsTest extends CIUnitTestCase
{
    /**
     * DashboardPageBuilder::buildMemberListData() derives its canEdit/canArchive/
     * canRestoreArchived flags from recordListRoleFlags(). A Viewer must come out
     * read-only on all three; only Developer/Admin get Archive/Restore.
     */
    public function testRecordListRoleFlagsPerRole(): void
    {
        $method = new \ReflectionMethod(DashboardPageBuilder::class, 'recordListRoleFlags');

        $builder = (new \ReflectionClass(DashboardPageBuilder::class))->newInstanceWithoutConstructor();

        $expected = [
            'Developer' => [true, true, true],
            'Admin'     => [true, true, true],
            'Encoder'   => [true, false, false],
            'Viewer'    => [false, false, false],
        ];

        foreach ($expected as $role => [$canEdit, $canArchive, $canRestoreArchived]) {
            [$actualEdit, $actualArchive, $actualRestore] = $method->invoke($builder, $role);

            $this->assertSame($canEdit, $actualEdit, "$role canEdit");
            $this->assertSame($canArchive, $actualArchive, "$role canArchive");
            $this->assertSame($canRestoreArchived, $actualRestore, "$role canRestoreArchived");
        }
    }

    /**
     * The Encoder dashboard activity panel must read only the session user's own
     * rows. AuditTrailsModel::getByUser() is `where('userID', $userId)`; a real
     * call needs a database, so this asserts the source calls that scoped method
     * (rather than, say, a bare `AuditTrailsModel()->stats()` or an unscoped
     * `builder()->get()`) for the Encoder branch that feeds $myAudits.
     */
    public function testEncoderActivityPanelIsScopedToTheSessionUser(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');

        $this->assertMatchesRegularExpression(
            '/\$myAudits\s*=\s*\$isDashboard\s*&&\s*\$currentRole\s*===\s*\'Encoder\'\s*\n\s*\?\s*\(new AuditTrailsModel\(\)\)->getByUser\(\(int\) session\(\)->get\(\'user_id\'\)/',
            $source,
            'Encoder dashboard activity must call AuditTrailsModel::getByUser() with the session user id'
        );
    }

    /**
     * app/Views/Pages/dashboard.php must keep gating the "My Recent Activity"
     * panel on the Encoder role, or every role starts seeing (or losing) it.
     */
    public function testDashboardViewGatesActivityPanelOnEncoderRole(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/Pages/dashboard.php');

        $this->assertStringContainsString("(\$role ?? '') === 'Encoder'", $view);
        $this->assertStringContainsString('My Recent Activity', $view);
    }

    /**
     * $seesDistribution must stay Developer/Admin only, deliberately narrower
     * than the Distribution *page* (Viewer-reachable via the manifest): the
     * dashboard's Subsidy Distribution section hits reports endpoints guarded
     * to Developer/Admin and surfaces per-scanner kiosk usernames, so it must
     * not be re-keyed to Navigation::pageRoles('distribution').
     */
    public function testSeesDistributionIsNarrowedToDeveloperAndAdmin(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');

        $this->assertStringContainsString(
            '$seesDistribution = $isDeveloper || $isAdmin;',
            $source,
            '$seesDistribution must be Developer/Admin only, not re-keyed to the navigation manifest'
        );
        $this->assertStringNotContainsString(
            "Navigation::pageRoles('distribution'), true)",
            $source,
            '$seesDistribution must not read the distribution page roles from the manifest'
        );
    }

    /**
     * app/Views/Pages/dashboard.php must gate the batch zone (batch-overview)
     * on the same $seesDistribution flag the builder computes. Task 11 cut the
     * separate distribution KPI tiles (wrong denominator, replaced by the batch
     * zone's own progress block), so only one gate remains.
     */
    public function testDashboardViewGatesDistributionSectionOnSeesDistribution(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/Pages/dashboard.php');

        $this->assertSame(
            1,
            substr_count($view, 'if ($seesDistribution)'),
            'the batch zone must gate on $seesDistribution'
        );
    }
}
