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
     * The dashboard is the landing page for all staff, so its two panes are
     * not role-gated any more and $seesDistribution is gone. A reinstated gate
     * would put a role back on an empty page.
     */
    public function testDashboardPanesAreNotRoleGated(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/Pages/dashboard.php');
        $builder = (string) file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');

        $this->assertStringNotContainsString('seesDistribution', $view);
        $this->assertStringNotContainsString('seesDistribution', $builder);
        $this->assertStringContainsString("view('Admin/batch-overview')", $view);
        $this->assertStringContainsString("view('Pages/dashboard-overview')", $view);
    }

    /**
     * The panes must still be assembled one at a time. The role condition went
     * away; the per-pane laziness that keeps Overview from paying for the batch
     * snapshot queries, and the reverse, did not.
     */
    public function testBuilderStillAssemblesOnlyThePaneBeingShown(): void
    {
        $builder = (string) file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');

        $this->assertStringContainsString("\$isDashboard && \$dashboardView === 'distribution'", $builder);
        $this->assertStringContainsString("\$isDashboard && \$dashboardView === 'overview'", $builder);
    }
}
